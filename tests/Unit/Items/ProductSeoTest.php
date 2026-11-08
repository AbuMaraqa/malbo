<?php

namespace Tests\Unit\Items;

use Modules\Items\Support\ProductSeo;
use PHPUnit\Framework\TestCase;

class ProductSeoTest extends TestCase
{
    public function test_pack_sets_the_locale_block(): void
    {
        $this->assertSame(
            ['seo' => ['ar' => ['title' => 'T', 'description' => 'D', 'keywords' => 'k1,k2']]],
            ProductSeo::pack(['meta_title' => 'T', 'meta_description' => 'D', 'meta_keywords' => 'k1,k2'], 'ar', null),
        );
    }

    public function test_seo_in_one_locale_keeps_the_other(): void
    {
        $existing = ['seo' => ['ar' => ['title' => 'ع', 'description' => null, 'keywords' => null]], 'other' => 1];

        $this->assertSame(
            ['seo' => ['ar' => ['title' => 'ع', 'description' => null, 'keywords' => null], 'en' => ['title' => 'E', 'description' => null, 'keywords' => null]], 'other' => 1],
            ProductSeo::pack(['meta_title' => 'E'], 'en', $existing),
        );
    }

    public function test_pack_without_text_changes_nothing(): void
    {
        $this->assertNull(ProductSeo::pack(['meta_title' => '', 'meta_description' => null], 'ar', null));
        $this->assertSame(['other' => 1], ProductSeo::pack([], 'ar', ['other' => 1]));
    }

    public function test_clearing_the_text_of_an_existing_locale_keeps_the_block_with_nulls(): void
    {
        $existing = ['seo' => ['ar' => ['title' => 'T', 'description' => 'D', 'keywords' => null]]];

        $this->assertSame(
            ['seo' => ['ar' => ['title' => null, 'description' => null, 'keywords' => null]]],
            ProductSeo::pack(['meta_title' => '', 'meta_description' => ''], 'ar', $existing),
        );
    }

    public function test_unpack_reads_the_locale_block(): void
    {
        $this->assertSame(
            ['meta_title' => 'T', 'meta_description' => null, 'meta_keywords' => null],
            ProductSeo::unpack(['seo' => ['ar' => ['title' => 'T']]], 'ar'),
        );
        $this->assertSame(
            ['meta_title' => null, 'meta_description' => null, 'meta_keywords' => null],
            ProductSeo::unpack(null, 'ar'),
        );
    }
}
