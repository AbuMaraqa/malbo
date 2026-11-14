<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\Product;
use Tests\DatabaseTestCase;

/**
 * Phase 7's columns and the indexes the shop's search and filters read through.
 */
class SearchFilterSchemaTest extends DatabaseTestCase
{
    public function test_the_new_columns_and_their_defaults(): void
    {
        $attribute = Attribute::factory()->create();
        $product = Product::factory()->create();

        $this->assertFalse($attribute->fresh()->is_filterable);
        $this->assertSame(0, $product->fresh()->sales_count);
    }

    public function test_the_filter_and_search_indexes_exist(): void
    {
        $definitions = DB::table('pg_indexes')->where('schemaname', 'public')->pluck('indexdef')->all();
        $has = fn (string $pattern) => collect($definitions)->contains(fn (string $definition) => (bool) preg_match($pattern, $definition));

        $this->assertTrue($has('/ON public\.items_product_attribute_value USING btree \(attribute_id, product_id\)/'));
        $this->assertTrue($has('/ON public\.items_products USING btree \(parent_id, status, is_active\)/'));
        $this->assertTrue($has('/ON public\.items_products USING btree \(sales_count\)/'));
        $this->assertTrue($has('/ON public\.items_products USING gin \(sku gin_trgm_ops\)/'));
        $this->assertTrue($has('/ON public\.items_tag_translations USING gin \(name gin_trgm_ops\)/'));

        foreach (['product', 'category', 'tag'] as $table) {
            $this->assertTrue($has("/ON public\\.items_{$table}_translations USING gin \\(items_search_norm\\(\\(name\\)::text\\) gin_trgm_ops\\)/"), $table);
        }

        $this->assertTrue($has('/UNIQUE INDEX items_attributes_one_size ON public\.items_attributes USING btree \(is_size\) WHERE is_size/'));
    }

    public function test_the_search_normalisation(): void
    {
        $normal = fn (string $text) => DB::selectOne('select items_search_norm(?) as value', [$text])->value;

        $this->assertSame('احمر', $normal('أحمر'));
        $this->assertSame('اسلام ايمان', $normal('إسلام آيمان'));
        $this->assertSame('سهره مستشفي', $normal('سهرة مستشفى'));
        $this->assertSame('قميص', $normal('قَمِيـــصٌ'));
        $this->assertSame('polo shirt', $normal('POLO Shirt'));
    }
}
