<?php

namespace Modules\Store\Support;

use App\Support\Locales;
use Closure;
use Illuminate\Support\Facades\URL;

/**
 * Runs code as if the request were in another language: translations, translated model fields and
 * the `{locale}` of every route() it builds. For messages written outside a request (the scheduler)
 * or for another buyer than the one asking (a stock alert in the subscriber's language).
 */
final class InLocale
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(?string $locale, Closure $callback): mixed
    {
        $locale = self::valid($locale);
        $previous = app()->getLocale();
        $defaults = URL::getDefaultParameters();

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
            URL::defaults(['locale' => $defaults['locale'] ?? $previous]);
        }
    }

    /**
     * A language the store speaks, else its default.
     */
    public static function valid(?string $locale): string
    {
        return in_array($locale, Locales::keys(), true) ? $locale : Locales::default();
    }
}
