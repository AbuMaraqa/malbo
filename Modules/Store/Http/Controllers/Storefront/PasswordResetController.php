<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\ForgotPasswordRequest;
use Modules\Store\Http\Requests\ResetPasswordRequest;
use Modules\Store\Services\CustomerAuthService;
use Modules\Store\Support\StorefrontLayout;

/**
 * A forgotten password, through Laravel's password broker. The request page says the same thing
 * whether or not the email has an account.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $auth,
        private readonly StorefrontLayout $layout,
    ) {}

    public function showForgot(Request $request): Response
    {
        return Inertia::render('Store/Storefront/Account/ForgotPassword', [
            ...$this->layout->props(['title' => __('Forgot your password?'), 'noindex' => true]),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->auth->sendResetLink($request->validated('email'));

        return back()->with('status', __('If an account uses this email, we sent it a link to choose a new password.'));
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Store/Storefront/Account/ResetPassword', [
            ...$this->layout->props(['title' => __('Choose a new password'), 'noindex' => true]),
            'token' => $token,
            'email' => is_string($request->query('email')) ? mb_substr($request->query('email'), 0, 160) : '',
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = $this->auth->reset($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('This reset link is not valid any more. Ask for a new one.')]);
        }

        return to_route('store.account.login')->with('status', __('Your password was changed. Sign in with the new one.'));
    }
}
