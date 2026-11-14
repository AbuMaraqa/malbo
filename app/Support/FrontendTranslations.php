<?php

namespace App\Support;

/**
 * The JSON translations a part of the site needs in the browser. The storefront only gets the
 * Store module's file, so buyers do not download the admin's texts; the wholesale portal gets the
 * Store's and its own.
 */
class FrontendTranslations
{
    /** Each storefront section's modules; a later file wins over an earlier one. */
    private const MODULES = [
        'storefront' => ['Store'],
        'wholesale' => ['Wholesale', 'Store'],
    ];

    /**
     * @return array<string, string>
     */
    public static function for(string $section): array
    {
        $locale = app()->getLocale();

        if ($section === 'admin') {
            return app('translator')->getLoader()->load($locale, '*', '*');
        }

        $texts = [];

        foreach (self::MODULES[$section] ?? ['Store'] as $module) {
            $path = base_path("Modules/{$module}/Lang/{$locale}.json");

            if (is_file($path)) {
                $texts = [...$texts, ...(json_decode((string) file_get_contents($path), true) ?? [])];
            }
        }

        return $texts;
    }
}
