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

    /**
     * @font-face rules for the self-hosted fonts (config/store.php `local_fonts`) whose files are in
     * public/: KO Sans, the MALBO Arabic font, is not on Google Fonts. Each face covers its
     * `unicode_range` only, so Latin text falls through to the next font in the stack. A file that
     * is not there yet adds no rule, and the stack's next font (Readex Pro) takes over.
     */
    public static function fontFaces(): string
    {
        $rules = '';

        foreach (config('store.local_fonts', []) as $family => $font) {
            foreach ($font['files'] as $weight => $name) {
                $found = self::fontFile(ltrim($name, '/'));

                if ($found === null) {
                    continue;
                }

                $rules .= sprintf(
                    "@font-face{font-family:'%s';src:url('/%s') format('%s');font-weight:%d;font-style:normal;font-display:swap;unicode-range:%s}",
                    $family, $found[0], $found[1], $weight, $font['unicode_range'],
                );
            }
        }

        return $rules;
    }

    /**
     * The first file of $name (a path under public/, no extension) that is there, smallest format first.
     *
     * @return array{0: string, 1: string}|null path, CSS format
     */
    private static function fontFile(string $name): ?array
    {
        foreach (['woff2' => 'woff2', 'woff' => 'woff', 'otf' => 'opentype', 'ttf' => 'truetype'] as $extension => $format) {
            if (is_file(public_path("{$name}.{$extension}"))) {
                return ["{$name}.{$extension}", $format];
            }
        }

        return null;
    }
}
