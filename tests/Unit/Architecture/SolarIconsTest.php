<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The system has one icon set: Solar, through resources/js/lib/icons.ts and navIcons.ts.
 */
class SolarIconsTest extends TestCase
{
    public function test_no_file_imports_another_icon_set(): void
    {
        $root = dirname(__DIR__, 3);
        $found = [];

        $sources = Finder::create()->files()->name(['*.vue', '*.ts'])->in([$root.'/resources/js', ...glob($root.'/Modules/*/resources/js')]);

        foreach ($sources as $source) {
            if (preg_match("~from\s+['\"](lucide-vue-next|@/tailadmin/icons/)~", $source->getContents())) {
                $found[] = $source->getRelativePathname();
            }
        }

        $this->assertSame([], $found);
    }
}
