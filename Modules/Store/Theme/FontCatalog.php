<?php

namespace Modules\Store\Theme;

/**
 * The storefront fonts a store can choose (config/store.php `fonts`).
 */
final class FontCatalog
{
    /**
     * @return array<string, array{name: string, family: string, stack: string}>
     */
    public static function all(): array
    {
        return config('store.fonts', []);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function stack(string $key): string
    {
        $fonts = self::all();

        return ($fonts[$key] ?? $fonts[config('store.default_font')])['stack'];
    }

    /**
     * One Google Fonts css2 URL for $keys (unknown keys skipped); $text limits it to those glyphs.
     *
     * @param  array<int, string>  $keys
     */
    public static function googleFontsUrl(array $keys, ?string $text = null): string
    {
        $families = array_map(
            fn (string $key) => 'family='.self::all()[$key]['family'],
            array_values(array_filter($keys, fn (string $key) => self::has($key))),
        );

        $url = 'https://fonts.googleapis.com/css2?'.implode('&', $families);

        if ($text !== null && $text !== '') {
            $url .= '&text='.rawurlencode($text);
        }

        return $url.'&display=swap';
    }
}
