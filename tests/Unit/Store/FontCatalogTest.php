<?php

namespace Tests\Unit\Store;

use Modules\Store\Theme\FontCatalog;
use Tests\TestCase;

class FontCatalogTest extends TestCase
{
    public function test_has_the_ten_fonts_with_tajawal_default(): void
    {
        $this->assertCount(10, FontCatalog::all());
        $this->assertSame('tajawal', config('store.default_font'));
        $this->assertTrue(FontCatalog::has('ibm-plex-sans-arabic'));
        $this->assertFalse(FontCatalog::has('comic-sans'));
    }

    public function test_google_fonts_url(): void
    {
        $url = FontCatalog::googleFontsUrl(['tajawal', 'ibm-plex-sans-arabic'], 'متجر');

        $this->assertStringStartsWith('https://fonts.googleapis.com/css2?family=Tajawal', $url);
        $this->assertStringContainsString('family=IBM+Plex+Sans+Arabic', $url);
        $this->assertStringContainsString('&text='.rawurlencode('متجر'), $url);
        $this->assertStringContainsString('display=swap', $url);
    }

    public function test_unknown_font_falls_back_to_the_default_stack(): void
    {
        $this->assertSame(FontCatalog::stack('tajawal'), FontCatalog::stack('nope'));
        $this->assertStringContainsString("'Tajawal'", FontCatalog::stack('tajawal'));
    }
}
