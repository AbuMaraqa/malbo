<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Locales;

class ApiLocalization
{
    /**
     * Handle an incoming request: the first supported language of Accept-Language, which may be
     * a bare code ("en") or what phones send ("en-US", "ar-EG,ar;q=0.9,en;q=0.8").
     */
    public function handle(Request $request, Closure $next)
    {
        $supportedLocales = \App\Support\Locales::keys();

        foreach ($request->getLanguages() as $language) {
            $locale = strtolower(strtok($language, '_'));

            if (in_array($locale, $supportedLocales, true)) {
                app()->setLocale($locale);
                break;
            }
        }

        return $next($request);
    }
}
