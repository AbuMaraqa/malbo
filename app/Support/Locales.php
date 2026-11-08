<?php

namespace App\Support;

/**
 * The languages the platform speaks. `{locale}` routes and the language switch read this list.
 */
class Locales
{
    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('app.locales'));
    }

    public static function default(): string
    {
        return config('app.locale');
    }

    /**
     * Route constraint for the `{locale}` parameter.
     */
    public static function pattern(): string
    {
        return implode('|', self::keys());
    }

    public static function isRtl(?string $locale = null): bool
    {
        return (config('app.locales')[$locale ?? app()->getLocale()]['dir'] ?? 'ltr') === 'rtl';
    }
}
