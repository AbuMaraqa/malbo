<?php

namespace Modules\Core\Support;

use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Theme\BrandColor;
use Modules\Store\Theme\FontCatalog;

/**
 * The look of the admin panel, chosen in the general settings: one color that becomes the whole
 * `brand-*` scale, and one font. The storefront has its own color and font (ThemeService).
 */
final class DashboardTheme
{
    public const DEFAULT_COLOR = '#465FFF';

    public const FONTS = ['tajawal', 'ibm-plex-sans-arabic'];

    /** Buttons keep white text, so a lighter color than this would make them unreadable. */
    public const MINIMUM_CONTRAST = 3.0;

    public function __construct(private readonly GeneralSettings $settings) {}

    public static function isReadable(string $hex): bool
    {
        return BrandColor::isValid($hex) && BrandColor::fromHex($hex)->contrastRatio('#FFFFFF') >= self::MINIMUM_CONTRAST;
    }

    public function css(): string
    {
        $color = self::isReadable($this->settings->dashboard_color) ? $this->settings->dashboard_color : self::DEFAULT_COLOR;
        $shades = BrandColor::fromHex($color)->shades();

        $vars = ['--font-sans' => FontCatalog::stack($this->font()), '--color-brand-25' => $shades[50], '--color-brand-950' => $shades[900]];

        foreach ($shades as $shade => $hex) {
            $vars["--color-brand-{$shade}"] = $hex;
        }

        return ':root{'.implode(';', array_map(fn ($name, $value) => "{$name}:{$value}", array_keys($vars), $vars)).'}';
    }

    public function fontsUrl(): string
    {
        return FontCatalog::googleFontsUrl([$this->font()]);
    }

    /**
     * @return list<array{value: string, label: string, stack: string}>
     */
    public static function fontOptions(): array
    {
        return array_map(fn (string $key) => [
            'value' => $key,
            'label' => FontCatalog::all()[$key]['name'],
            'stack' => FontCatalog::stack($key),
        ], self::FONTS);
    }

    private function font(): string
    {
        return in_array($this->settings->dashboard_font, self::FONTS, true) ? $this->settings->dashboard_font : self::FONTS[0];
    }
}
