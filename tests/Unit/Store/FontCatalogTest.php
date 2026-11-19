<?php

namespace Tests\Unit\Store;

use Modules\Store\Theme\FontCatalog;
use Tests\TestCase;

class FontCatalogTest extends TestCase
{
    public function test_has_the_fonts_with_the_malbo_pair_by_default(): void
    {
        $this->assertCount(11, FontCatalog::all());
        $this->assertSame('malbo', config('store.default_font'));
        $this->assertSame("'KO Sans', 'Readex Pro', system-ui, sans-serif", FontCatalog::stack('malbo'));
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

    public function test_self_hosted_faces_cover_only_the_files_that_are_there(): void
    {
        $dir = sys_get_temp_dir().'/font-faces-'.uniqid();
        mkdir($dir.'/fonts/ko-sans', 0777, true);
        file_put_contents($dir.'/fonts/ko-sans/ko-sans-regular.otf', 'otf');
        $this->app->usePublicPath($dir);

        $css = FontCatalog::fontFaces();

        $this->assertSame(1, substr_count($css, '@font-face'));
        $this->assertStringContainsString("font-family:'KO Sans';src:url('/fonts/ko-sans/ko-sans-regular.otf') format('opentype');font-weight:400", $css);
        $this->assertStringContainsString('unicode-range:U+0600-06FF', $css);

        // The smaller format wins once it is there.
        file_put_contents($dir.'/fonts/ko-sans/ko-sans-regular.woff2', 'woff2');
        $this->assertStringContainsString("url('/fonts/ko-sans/ko-sans-regular.woff2') format('woff2')", FontCatalog::fontFaces());

        unlink($dir.'/fonts/ko-sans/ko-sans-regular.woff2');
        unlink($dir.'/fonts/ko-sans/ko-sans-regular.otf');
        $this->assertSame('', FontCatalog::fontFaces());
    }

    public function test_unknown_font_falls_back_to_the_default_stack(): void
    {
        $this->assertSame(FontCatalog::stack('malbo'), FontCatalog::stack('nope'));
        $this->assertStringContainsString("'Tajawal'", FontCatalog::stack('tajawal'));
    }
}
