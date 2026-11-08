<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Every admin table gives its users filters. A page with a DataTable must pass a `filters` list
 * and the list must not be empty.
 */
class TableFiltersTest extends TestCase
{
    public function test_every_data_table_declares_filters(): void
    {
        $root = dirname(__DIR__, 3);
        $missing = [];

        $pages = Finder::create()->files()->name('*.vue')->in([$root.'/Modules'])->path('resources/js/Pages');

        foreach ($pages as $page) {
            $source = $page->getContents();

            if (! str_contains($source, '<DataTable')) {
                continue;
            }

            if (! preg_match('/<DataTable[^>]*:filters="/s', $source)
                || preg_match('/computed<TableFilter\[\]>\(\(\)\s*=>\s*\[\s*\]\)/', $source)
                || preg_match('/:filters="\[\s*\]"/', $source)) {
                $missing[] = $page->getRelativePathname();
            }
        }

        $this->assertSame([], $missing, 'These tables have no filters: '.implode(', ', $missing));
    }
}
