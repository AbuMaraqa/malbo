<?php

namespace Tests\Feature\Wholesale;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The portal's Vue pages are storefront pages: the store's look, and only the texts the portal is
 * sent (the Store's and the Wholesale module's).
 */
class PortalFrontendTest extends TestCase
{
    public function test_every_portal_text_is_sent_to_the_portal(): void
    {
        $translated = [];
        foreach (['Store', 'Wholesale'] as $module) {
            $translated += json_decode((string) file_get_contents($this->root()."/Modules/{$module}/Lang/ar.json"), true, flags: JSON_THROW_ON_ERROR);
        }

        $missing = [];

        foreach ($this->sources() as $path => $source) {
            preg_match_all("/\bt\(\s*'((?:[^'\\\\]|\\\\.)+)'/", $source, $matches);

            foreach ($matches[1] as $key) {
                if (! isset($translated[stripslashes($key)])) {
                    $missing[stripslashes($key)] = $path;
                }
            }
        }

        $this->assertSame([], $missing, 'The portal only gets Modules/Store/Lang and Modules/Wholesale/Lang');
    }

    public function test_the_portal_has_the_store_look(): void
    {
        foreach ($this->sources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression('/\bbrand-\d|\b(bg|text|border)-brand\b/', $source, "{$path} uses the admin color");
            $this->assertDoesNotMatchRegularExpression('/\bdark:/', $source, "{$path} has dark styles");
            $this->assertStringNotContainsString('AdminLayout', $source, $path);
            $this->assertStringNotContainsString('@/Components/ui/', $source, $path);
            $this->assertStringNotContainsString('@/Components/form/', $source, $path);
            $this->assertDoesNotMatchRegularExpression('/\b(window|document|localStorage)\b\s*[.\[]/', $source, "{$path} touches a browser global");
        }
    }

    /**
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        foreach (Finder::create()->files()->name(['*.vue', '*.ts'])->in($this->root().'/Modules/Wholesale/resources/js/Pages/Portal') as $file) {
            $sources[$file->getRelativePathname()] = $file->getContents();
        }

        return $sources;
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
