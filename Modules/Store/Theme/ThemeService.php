<?php

namespace Modules\Store\Theme;

use Illuminate\Support\Facades\Cache;
use Modules\Store\Settings\StoreAppearanceSettings;

/**
 * The store's runtime theme from its color and font: CSS variables inlined in the storefront
 * layout's <head>, and the same values as tokens for the mobile app API. Cached in the store's
 * own cache until the appearance changes.
 */
class ThemeService
{
    private const CACHE_KEY = 'store.theme.tokens';

    /**
     * @return array{color: string, on_color: string, shades: array<int, string>, font: array{key: string, name: string, stack: string}}
     */
    public function tokens(): array
    {
        return Cache::rememberForever(self::key(), function (): array {
            $settings = app(StoreAppearanceSettings::class);
            $color = BrandColor::isValid($settings->primary_color)
                ? BrandColor::fromHex($settings->primary_color)
                : BrandColor::fromHex(config('store.default_color'));
            $font = FontCatalog::has($settings->font) ? $settings->font : config('store.default_font');

            return [
                'color' => $color->hex(),
                'on_color' => $color->onBrand(),
                'shades' => $color->shades(),
                'font' => ['key' => $font, 'name' => FontCatalog::all()[$font]['name'], 'stack' => FontCatalog::stack($font)],
            ];
        });
    }

    public function css(): string
    {
        $tokens = $this->tokens();

        $vars = ['--brand' => $tokens['color'], '--brand-contrast' => $tokens['on_color']];
        foreach ($tokens['shades'] as $shade => $hex) {
            $vars["--brand-{$shade}"] = $hex;
        }
        $vars['--font-sans'] = $tokens['font']['stack'];

        return ':root{'.implode(';', array_map(fn ($name, $value) => "{$name}:{$value}", array_keys($vars), $vars)).'}';
    }

    public function fontsUrl(): ?string
    {
        return FontCatalog::googleFontsUrl([$this->tokens()['font']['key']]);
    }

    public function forget(): void
    {
        Cache::forget(self::key());
    }

    /** Per store also by key, should the cache store ever be shared between stores. */
    private static function key(): string
    {
        return self::CACHE_KEY.'.'.(tenant()?->getTenantKey() ?? 'central');
    }
}
