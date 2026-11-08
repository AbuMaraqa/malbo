<?php

namespace Tests\Feature\Store;

use InvalidArgumentException;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Theme\BrandColor;
use Modules\Store\Theme\ThemeService;
use Tests\TenantTestCase;

class ThemeServiceTest extends TenantTestCase
{
    public function test_css_has_the_brand_shades_contrast_and_font(): void
    {
        $css = app(ThemeService::class)->css();

        $this->assertStringContainsString('--brand:#1F6F50', $css);
        $this->assertStringContainsString('--brand-50:'.BrandColor::fromHex('#1F6F50')->shades()[50], $css);
        $this->assertStringContainsString('--brand-contrast:#FFFFFF', $css);
        $this->assertStringContainsString("--font-sans:'Tajawal'", $css);
        $this->assertStringContainsString('family=Tajawal', app(ThemeService::class)->fontsUrl());
    }

    public function test_saving_appearance_changes_the_css(): void
    {
        app(ThemeService::class)->css(); // warm the cache

        app(StoreAppearanceService::class)->save(['primary_color' => '#777777', 'font' => 'cairo'], [], []);

        $css = app(ThemeService::class)->css();
        $this->assertStringContainsString('--brand:#777777', $css);
        $this->assertStringContainsString('--brand-contrast:#111827', $css);
        $this->assertStringContainsString("--font-sans:'Cairo'", $css);
        $this->assertStringContainsString('family=Cairo', app(ThemeService::class)->fontsUrl());
    }

    public function test_invalid_color_or_font_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(StoreAppearanceService::class)->save(['primary_color' => 'red'], [], []);
    }

    public function test_unknown_font_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(StoreAppearanceService::class)->save(['font' => 'comic-sans'], [], []);
    }
}
