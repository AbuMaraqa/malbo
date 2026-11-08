<?php

namespace App\Http\Responses;

use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;

/**
 * After login, staff land on the store admin (route `dashboard`, /{locale}/admin) in the
 * current locale, unless they were on their way to another page.
 */
class LoginResponse extends FortifyLoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return parent::toResponse($request);
        }

        return redirect()->intended(route('dashboard', ['locale' => app()->getLocale()]));
    }
}
