<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Arabic is the default language: every t('...') text in a Vue page or component has an Arabic
 * translation in some `Modules/<M>/Lang/ar.json`, or in `lang/ar.json` for the platform's own pages.
 */
class ArabicTextsTest extends TestCase
{
    public function test_every_frontend_text_has_an_arabic_translation(): void
    {
        $root = dirname(__DIR__, 3);

        $translated = [];
        foreach ([$root.'/lang/ar.json', ...glob($root.'/Modules/*/Lang/ar.json')] as $file) {
            $translated += json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        }

        $sources = Finder::create()->files()->name(['*.vue', '*.ts'])
            ->in([$root.'/resources/js', ...glob($root.'/Modules/*/resources/js')])
            ->exclude('tailadmin');

        $missing = [];
        foreach ($sources as $source) {
            preg_match_all("/\bt\(\s*'((?:[^'\\\\]|\\\\.)+)'/", $source->getContents(), $matches);

            foreach ($matches[1] as $key) {
                $key = stripslashes($key);

                if (! isset($translated[$key])) {
                    $missing[$key] = $source->getRelativePathname();
                }
            }
        }

        ksort($missing);

        $this->assertSame([], $missing, 'Missing from Lang/ar.json');
    }
}
