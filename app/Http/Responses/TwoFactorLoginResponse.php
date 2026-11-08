<?php

namespace App\Http\Responses;

use Laravel\Fortify\Http\Responses\TwoFactorLoginResponse as FortifyTwoFactorLoginResponse;

/**
 * After the two-factor challenge, staff land on the store admin like after a plain login.
 */
class TwoFactorLoginResponse extends FortifyTwoFactorLoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return parent::toResponse($request);
        }

        return redirect()->intended(route('dashboard', ['locale' => app()->getLocale()]));
    }
}
