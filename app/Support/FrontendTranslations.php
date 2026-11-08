<?php

namespace App\Support;

/**
 * The JSON translations a part of the site needs in the browser. The storefront only gets the
 * Store module's file, so buyers do not download the admin's texts.
 */
class FrontendTranslations
{
    /**
     * @return array<string, string>
     */
    public static function for(string $section): array
    {
        $locale = app()->getLocale();

        if ($section === 'admin') {
            return app('translator')->getLoader()->load($locale, '*', '*');
        }

        $path = module_path('Store', "Lang/{$locale}.json");

        return is_file($path) ? json_decode((string) file_get_contents($path), true) ?? [] : [];
    }
}
