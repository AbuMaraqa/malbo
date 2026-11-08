<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Every page a controller renders has its Vue file, in its module or in resources/js/Pages.
 */
class InertiaPagesExistTest extends TestCase
{
    private const MODULES = ['Core', 'Items', 'GeoLocation', 'Branch', 'Store'];

    public function test_every_rendered_page_has_a_vue_file(): void
    {
        $root = dirname(__DIR__, 3);
        $missing = [];

        foreach (Finder::create()->files()->name('*.php')->in([$root.'/app', $root.'/Modules', $root.'/bootstrap'])->exclude('cache') as $file) {
            preg_match_all("/Inertia::render\(\s*'([^']+)'/", $file->getContents(), $matches);

            foreach ($matches[1] as $page) {
                [$first, $rest] = explode('/', $page, 2) + [1 => ''];

                $path = in_array($first, self::MODULES, true)
                    ? "{$root}/Modules/{$first}/resources/js/Pages/{$rest}.vue"
                    : "{$root}/resources/js/Pages/{$page}.vue";

                if (! is_file($path)) {
                    $missing[$page] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
