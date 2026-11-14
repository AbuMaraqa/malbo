<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\CustomerLoginRequest;
use Modules\Store\Http\Requests\CustomerRegisterRequest;
use Modules\Store\Services\CustomerAuthService;
use Modules\Store\Support\StorefrontLayout;

/**
 * The storefront's sign-in, sign-up and sign-out. A page that needed an account sends the guest
 * here and gets them back after (the intended URL); a product's heart sends `?back=<path>`.
 */
class AccountAuthController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $auth,
        private readonly StorefrontLayout $layout,
    ) {}

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return to_route('store.account');
        }

        $back = $request->query('back');

        // Only a path on this site: `//host` and a backslash (`/\host`) would leave it.
        if (is_string($back) && str_starts_with($back, '/') && ! str_starts_with($back, '//') && ! str_contains($back, chr(92))) {
            $request->session()->put('url.intended', url($back));
        }

        return Inertia::render('Store/Storefront/Account/Login', [
            ...$this->layout->props(['title' => __('Sign in'), 'noindex' => true]),
            'status' => $request->session()->get('status'),
            // Two-factor accounts are sent there (the storefront has no second step).
            'staffLoginUrl' => route('login'),
        ]);
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->auth->login($data['email'], $data['password'], (bool) ($data['remember'] ?? false), $request);

        return redirect()->intended(route('store.account'));
    }

    public function showRegister(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return to_route('store.account');
        }

        return Inertia::render('Store/Storefront/Account/Register', [
            ...$this->layout->props(['title' => __('Create an account'), 'noindex' => true]),
        ]);
    }

    public function register(CustomerRegisterRequest $request): RedirectResponse
    {
        $this->auth->register($request->validated(), $request);

        return redirect()->intended(route('store.account'))->with('success', __('Welcome! Your account is ready.'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return to_route('home');
    }
}
