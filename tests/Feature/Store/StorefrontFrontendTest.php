<?php

namespace Tests\Feature\Store;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Rules of the storefront's Vue code: its own look, safe under SSR, its texts in the Store module.
 */
class StorefrontFrontendTest extends TestCase
{
    public function test_every_storefront_text_is_in_the_store_module(): void
    {
        $translated = json_decode((string) file_get_contents($this->root().'/Modules/Store/Lang/ar.json'), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach ($this->sources() as $path => $source) {
            preg_match_all("/\bt\(\s*'((?:[^'\\\\]|\\\\.)+)'/", $source, $matches);

            foreach ($matches[1] as $key) {
                if (! isset($translated[stripslashes($key)])) {
                    $missing[stripslashes($key)] = $path;
                }
            }
        }

        $this->assertSame([], $missing, 'Storefront pages only get Modules/Store/Lang');
    }

    public function test_the_storefront_has_its_own_look(): void
    {
        foreach ($this->sources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression('/\bbrand-\d|\b(bg|text|border)-brand\b/', $source, "{$path} uses the admin color");
            $this->assertDoesNotMatchRegularExpression('/\bdark:/', $source, "{$path} has dark styles");
            $this->assertStringNotContainsString('@/tailadmin', $source, $path);
            $this->assertStringNotContainsString('AdminLayout', $source, $path);
            $this->assertStringNotContainsString('@/Components/ui/', $source, $path);
            $this->assertStringNotContainsString('@/Components/form/', $source, $path);
        }
    }

    public function test_browser_globals_stay_out_of_setup(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (! preg_match('/\b(window|document|localStorage|navigator)\b\s*[.\[]|\bwindow\b/', $source)) {
                continue;
            }

            $this->assertContains(basename($path), ['StorefrontHeader.vue', 'useReveal.ts'], "{$path} touches a browser global");
            $this->assertStringContainsString('onMounted', $source, $path);
        }
    }

    /**
     * @return array<string, string> path => source
     */
    private function sources(): array
    {
        $root = $this->root();
        $sources = ['StorefrontLayout.vue' => (string) file_get_contents($root.'/resources/js/Layouts/StorefrontLayout.vue')];

        $finder = Finder::create()->files()->name(['*.vue', '*.ts'])->in([$root.'/resources/js/Components/storefront', $root.'/Modules/Store/resources/js/Pages/Storefront']);

        foreach ($finder as $file) {
            $sources[$file->getRelativePathname()] = $file->getContents();
        }

        return $sources;
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
