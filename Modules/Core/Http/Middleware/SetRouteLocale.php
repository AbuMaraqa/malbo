<?php

namespace Modules\Core\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the locale per request from the `{locale}` route prefix, remembers it in the session and
 * fills `{locale}` in every route() call. Keeps `{locale}` out of controller arguments.
 */
class SetRouteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $locale = $route?->parameter('locale');

        if (! in_array($locale, Locales::keys(), true)) {
            $remembered = $request->hasSession() ? $request->session()->get('locale') : null;
            $locale = in_array($remembered, Locales::keys(), true) ? $remembered : Locales::default();
        } elseif ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        $route?->forgetParameter('locale');

        return $next($request);
    }
}
