<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admin sign-in (`/{locale}/platform/login`, guard `platform`).
 *
 * Two limits: 5 failures a minute per email and address, and 20 an hour per email from any
 * address (a changing or spoofed address cannot reset that one).
 */
class LoginController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const MAX_ATTEMPTS_PER_EMAIL = 20;

    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('platform')->check()) {
            return redirect(self::dashboardUrl());
        }

        return Inertia::render('Platform/Login', ['loginUrl' => self::loginUrl()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower($credentials['email']);
        $limits = [
            self::limiterKey($email, (string) $request->ip()) => [self::MAX_ATTEMPTS, 60],
            self::limiterKey($email) => [self::MAX_ATTEMPTS_PER_EMAIL, 3600],
        ];

        foreach ($limits as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'email' => __('Too many sign-in attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        // PostgreSQL compares exactly: emails are saved in lower case, and looked up the same way.
        if (! Auth::guard('platform')->attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            foreach ($limits as $key => [, $decay]) {
                RateLimiter::hit($key, $decay);
            }

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        RateLimiter::clear(array_key_first($limits));
        $request->session()->regenerate();

        return redirect(self::dashboardUrl());
    }

    public static function limiterKey(string $email, ?string $ip = null): string
    {
        return 'platform-login:'.Str::lower($email).($ip === null ? '' : '|'.$ip);
    }

    /**
     * On the host the admin is on: platform sessions are host-only, and route() would point at the
     * first central domain.
     */
    public static function dashboardUrl(): string
    {
        return url('/'.app()->getLocale().'/platform');
    }

    public static function loginUrl(?string $locale = null): string
    {
        return url('/'.($locale ?? app()->getLocale()).'/platform/login');
    }
}
