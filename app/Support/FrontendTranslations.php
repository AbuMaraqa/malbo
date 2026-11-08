<?php

namespace App\Support;

/**
 * The JSON translations a part of the site needs in the browser. The storefront only gets the
 * Store module's file and the public platform pages add `lang/<locale>.json`, so buyers do not
 * download the admin's texts. The platform's own admin uses the admin's tables and texts.
 */
class FrontendTranslations
{
    /**
     * @return array<string, string>
     */
    public static function for(string $section): array
    {
        $locale = app()->getLocale();

        if (in_array($section, ['admin', 'platform-admin'], true)) {
            return app('translator')->getLoader()->load($locale, '*', '*');
        }

        $files = [module_path('Store', "Lang/{$locale}.json")];

        if ($section === 'platform') {
            $files[] = lang_path("{$locale}.json");
        }

        $texts = [];
        foreach ($files as $path) {
            if (is_file($path)) {
                $texts = [...$texts, ...json_decode((string) file_get_contents($path), true) ?? []];
            }
        }

        return $texts;
    }
}
