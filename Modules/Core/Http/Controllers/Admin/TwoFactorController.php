<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Two-factor setup from the profile page, on Fortify's own actions. Each sensitive step asks for
 * the password in the same request instead of Fortify's separate confirm-password screen.
 */
class TwoFactorController extends Controller
{
    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $this->confirmPassword($request);

        $enable($request->user());

        return to_route('profile.index');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:10']])['code'];

        try {
            $confirm($request->user(), $code);
        } catch (ValidationException $exception) {
            // Fortify reports it in its own error bag; the form reads the default one.
            throw ValidationException::withMessages(['code' => $exception->errors()['code'] ?? [__('The provided two factor authentication code was invalid.')]]);
        }

        return to_route('profile.index')
            ->with('success', __('Two-factor authentication is on.'))
            ->with('two_factor_show_recovery_codes', true);
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $this->confirmPassword($request);

        $disable($request->user());

        return to_route('profile.index')->with('success', __('Two-factor authentication is off.'));
    }

    public function recoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $this->confirmPassword($request);

        abort_unless($request->user()->hasEnabledTwoFactorAuthentication(), 404);

        $generate($request->user());

        return to_route('profile.index')->with('two_factor_show_recovery_codes', true);
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']]);

        $request->session()->passwordConfirmed();
    }
}
