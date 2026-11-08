<?php

namespace Tests\Unit\Store;

use InvalidArgumentException;
use Modules\Store\Theme\BrandColor;
use Tests\TestCase;

class BrandColorTest extends TestCase
{
    public function test_contrast_of_black_on_white_is_21(): void
    {
        $this->assertEqualsWithDelta(21.0, BrandColor::fromHex('#000000')->contrastRatio(), 0.01);
    }

    public function test_text_on_brand_flips_at_4_5(): void
    {
        $this->assertSame('#FFFFFF', BrandColor::fromHex('#767676')->onBrand()); // 4.54
        $this->assertSame('#111827', BrandColor::fromHex('#777777')->onBrand()); // 4.48
    }

    public function test_every_preset_is_valid_and_readable(): void
    {
        foreach (config('store.color_presets') as $hex) {
            $this->assertTrue(BrandColor::isValid($hex), $hex);
            $this->assertSame('#FFFFFF', BrandColor::fromHex($hex)->onBrand(), $hex);
        }
    }

    public function test_rejects_bad_hex(): void
    {
        $this->assertFalse(BrandColor::isValid('#12345'));
        $this->assertFalse(BrandColor::isValid('red'));
        $this->assertTrue(BrandColor::isValid('#1f6f50'));
        $this->assertSame('#1F6F50', BrandColor::fromHex('#1f6f50')->hex());

        $this->expectException(InvalidArgumentException::class);
        BrandColor::fromHex('#GGGGGG');
    }

    public function test_shades_run_from_light_to_dark_around_the_color(): void
    {
        $shades = BrandColor::fromHex('#1F6F50')->shades();

        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900], array_keys($shades));
        $this->assertSame('#1F6F50', $shades[500]);
        $lightness = fn (string $hex) => BrandColor::fromHex($hex)->contrastRatio('#000000');
        $this->assertGreaterThan($lightness($shades[900]), $lightness($shades[50]));
        $this->assertGreaterThan($lightness($shades[600]), $lightness($shades[400]));
    }

    public function test_dark_shades_never_get_lighter_than_the_color(): void
    {
        $shades = BrandColor::fromHex('#000000')->shades();

        foreach ([600, 700, 800, 900] as $shade) {
            $this->assertSame('#000000', $shades[$shade]);
        }
    }
}
