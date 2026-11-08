<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Locales;

/**
 * `/` on every host -> `/{locale}`: the locale kept in the session (localeSessionRedirect
 * stores it), else the default one. Stays on the request's host.
 */
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = $request->session()->get('locale');

        if (! in_array($locale, \App\Support\Locales::keys(), true)) {
            $locale = \App\Support\Locales::default();
        }

        $query = $request->getQueryString();

        return redirect()->to('/'.$locale.($query ? '?'.$query : ''));
    }
}
