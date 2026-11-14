<?php

namespace Modules\Store\Services;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Store\Notifications\CustomerResetPassword;
use Modules\Store\Support\CartCookie;
use Modules\Store\Support\Phone;

/**
 * Buyers' accounts on the storefront: sign up (always the Customer role), sign in, sign out and a
 * forgotten password. Staff keep Fortify's /login. A staff member without two-factor may sign in
 * here; an account with two-factor never is, as this form has no second step: it is sent to the
 * staff login. Signing in hands the browser's cart to the account (CartService::adopt).
 *
 * Sign-in is limited as Fortify limits it: LOGIN_ATTEMPTS failures a minute for one email from one
 * address, then a message until the minute is over; a success clears the count.
 */
class CustomerAuthService
{
    public const LOGIN_ATTEMPTS = 5;

    /** A bcrypt hash of nothing anyone types: checked when the email has no account. */
    private const DUMMY_HASH = '$2y$12$GO8BBqUCW1Ys3jaLkTHE/.0ZdwZHPB5lS4oQC0oBYphDIhZ9sm626';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CartService $cart,
    ) {}

    /**
     * @param  array{name: string, phone: string, email: string, password: string}  $data
     */
    public function register(array $data, Request $request): User
    {
        try {
            $user = DB::transaction(function () use ($data): User {
                /** @var User $user */
                $user = $this->users->create([
                    'name' => trim($data['name']),
                    'email' => mb_strtolower(trim($data['email'])),
                    'phone' => Phone::canonical($data['phone']),
                    'password' => $data['password'], // the model's `hashed` cast hashes it
                ]);

                $user->assignRole(UserRole::CUSTOMER->value);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Two sign-ups with one email at once: the form check passed for both, the unique
            // index on lower(email) refused the second.
            throw ValidationException::withMessages(['email' => __('An account with this email already exists. Sign in instead.')]);
        }

        event(new Registered($user));

        $this->signIn($user, false, $request);

        return $user;
    }

    /**
     * @throws ValidationException on `email`: wrong pair, or too many attempts
     */
    public function login(string $email, string $password, bool $remember, Request $request): User
    {
        $key = self::throttleKey($email, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages(['email' => __('Too many sign-in attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ])]);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            // As long as a real check: the time taken does not tell whether the account exists.
            Hash::check($password, self::DUMMY_HASH);
        }

        if ($user === null || ! Auth::guard('web')->getProvider()->validateCredentials($user, ['password' => $password])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => __('The email or password is not correct.')]);
        }

        // Two-factor accounts (staff) only sign in where the second step is asked: Fortify's /login.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['email' => __('Staff accounts with two-factor sign in at the staff login')]);
        }

        RateLimiter::clear($key);
        $this->signIn($user, $remember, $request);

        return $user;
    }

    /**
     * The cart stays with the account: this browser starts a fresh one.
     */
    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        CartCookie::forget();
    }

    /**
     * Mails a reset link that opens on the storefront, in the current language. Whether the email
     * has an account is never told: the caller shows the same message either way.
     */
    public function sendResetLink(string $email): void
    {
        $locale = app()->getLocale();

        // After the response: an answer that took longer for a real account would tell it exists.
        defer(function () use ($email, $locale): void {
            $user = $this->users->findByEmail($email);

            if ($user === null) {
                return;
            }

            Password::broker()->sendResetLink(
                ['email' => $user->email],
                fn (User $user, string $token) => $user->notify((new CustomerResetPassword($token, $locale))->locale($locale)),
            );
        }, always: true);
    }

    /**
     * @param  array{token: string, email: string, password: string}  $data
     * @return string the broker's status: Password::PASSWORD_RESET when it worked
     */
    public function reset(array $data): string
    {
        $user = $this->users->findByEmail($data['email']);

        return Password::broker()->reset(
            ['email' => $user?->email ?? $data['email'], 'token' => $data['token'], 'password' => $data['password']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            },
        );
    }

    private function signIn(User $user, bool $remember, Request $request): void
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $this->cart->adopt($request, $user);
    }

    private static function throttleKey(string $email, string $ip): string
    {
        return 'store-login:'.Str::transliterate(Str::lower(trim($email))).'|'.$ip;
    }
}
