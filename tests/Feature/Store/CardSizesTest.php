<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Services\InventoryService;
use Modules\Store\Services\StorefrontCatalog;

/**
 * The sizes a product card lists: the size attribute's values on the variants a buyer can see, a
 * size no such variant can order struck through.
 */
class CardSizesTest extends StorefrontTestCase
{
    private const SHOP = 'Store/Storefront/Shop';

    public function test_cards_list_the_sizes_of_visible_variants_in_order(): void
    {
        $size = $this->attribute('size');
        [$s, $m, $l, $xl] = $this->values($size, ['S', 'M', 'L', 'XL']);
        $color = $this->attribute('color', AttributeType::COLOR);
        [$black] = $this->values($color, ['أسود']);
        $hidden = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'مخفي', 'is_active' => false]);

        $product = Product::factory()->create();
        $this->variant($product, [$l, $black]);
        $this->variant($product, [$s, $black]);
        $this->variant($product, [$m, $black]);
        $this->variant($product, [$xl, $black], ['is_active' => 0]);
        $this->variant($product, [$xl, $hidden]);

        $this->assertSame([
            ['name' => 'S', 'available' => true],
            ['name' => 'M', 'available' => true],
            ['name' => 'L', 'available' => true],
        ], $this->sizes()[$product->id]);
    }

    public function test_a_size_no_variant_can_order_is_marked_unavailable(): void
    {
        $size = $this->attribute('size');
        [$s, $m, $l] = $this->values($size, ['S', 'M', 'L']);
        $product = Product::factory()->create();
        $this->variant($product, [$s], ['stock_quantity' => 0]);
        $this->variant($product, [$s], ['stock_quantity' => 2]);
        $this->variant($product, [$m], ['stock_quantity' => 0]);
        $this->variant($product, [$l], ['stock_quantity' => 0, 'allow_backorder' => true]);

        $this->assertSame([['name' => 'S', 'available' => true], ['name' => 'M', 'available' => false], ['name' => 'L', 'available' => true]], $this->sizes()[$product->id]);
    }

    public function test_no_sizes_without_a_size_attribute(): void
    {
        $color = $this->attribute('color', AttributeType::COLOR);
        $size = $this->attribute('size', size: false);
        $product = Product::factory()->create();
        $this->variant($product, [$this->values($color, ['أسود'])[0], $this->values($size, ['M'])[0]]);
        $simple = Product::factory()->create();

        $sizes = $this->sizes();
        $this->assertSame([], $sizes[$product->id]);
        $this->assertSame([], $sizes[$simple->id]);
    }

    public function test_cards_use_the_attribute_marked_as_the_size(): void
    {
        $fit = $this->attribute('fit');
        $size = $this->attribute('size', size: false);
        $product = Product::factory()->create(['slug' => 'polo']);
        $this->variant($product, [$this->values($fit, ['Slim'])[0], $this->values($size, ['M'])[0]]);

        // None marked: no guessing by slug or order.
        $this->assertSame([], $this->sizes()[$product->id]);

        $fit->forceFill(['is_size' => true])->save();

        $this->assertSame(['Slim'], array_column($this->sizes()[$product->id], 'name'));

        // The product page puts the size guide by the same attribute.
        $attributes = $this->props('/ar/product/polo', 'Store/Storefront/ProductPage')['options']['attributes'];
        $this->assertTrue(collect($attributes)->firstWhere('id', $fit->id)['size']);
        $this->assertFalse(collect($attributes)->firstWhere('id', $size->id)['size']);
    }

    public function test_a_stock_change_refreshes_the_cached_sizes(): void
    {
        $size = $this->attribute('size');
        [$m] = $this->values($size, ['M']);
        $product = Product::factory()->create();
        $variant = $this->variant($product, [$m], ['stock_quantity' => 1]);

        $this->assertTrue($this->sizes()[$product->id][0]['available']);

        app(InventoryService::class)->setLevel($variant, 0, \Modules\Items\Enums\StockMovementType::ADJUST);

        $this->assertFalse($this->sizes()[$product->id][0]['available']);
    }

    public function test_related_cards_are_cached(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create();
        $product->categories()->attach($category->id);
        Product::factory()->count(3)->create()->each(fn (Product $other) => $other->categories()->attach($category->id));
        $catalog = app(StorefrontCatalog::class);

        $first = $catalog->related($product)->map->toArray()->all();

        DB::enableQueryLog();
        $second = $catalog->related($product)->map->toArray()->all();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertCount(3, $second);
        // The cache store is the database: only its own reads remain.
        $this->assertSame([], array_values(array_filter(array_column($queries, 'query'), fn (string $sql) => ! str_contains($sql, 'from "cache"'))));
    }

    /**
     * `size` defaults to the slug being "size": the attribute staff marked as the size.
     */
    private function attribute(string $slug, AttributeType $type = AttributeType::SELECT, bool $filterable = true, ?bool $size = null): Attribute
    {
        return Attribute::factory()->create(['slug' => $slug, 'name' => $slug, 'type' => $type->value, 'is_filterable' => $filterable, 'is_size' => $size ?? $slug === 'size']);
    }

    /**
     * @param  list<string>  $names
     * @return list<AttributeValue>
     */
    private function values(Attribute $attribute, array $names): array
    {
        return array_map(fn (string $name, int $order) => AttributeValue::factory()->create([
            'attribute_id' => $attribute->id, 'name' => $name, 'sort_order' => $order, 'is_active' => true,
        ]), $names, array_keys($names));
    }

    /**
     * @param  list<AttributeValue>  $values
     */
    private function variant(Product $product, array $values, array $columns = []): Product
    {
        $variant = Product::factory()->variationOf($product)->create(['manage_stock' => true, 'stock_quantity' => 5, ...$columns]);
        $variant->attributeValues()->attach(array_map(fn (AttributeValue $value) => $value->id, $values));
        $product->touch(); // as the product form saves the parent with its variants

        return $variant;
    }

    /**
     * @return array<int, list<array{name: string, available: bool}>> product id => its card's sizes
     */
    private function sizes(): array
    {
        $cards = $this->props('/ar/shop', self::SHOP)['products']['data'];

        return array_combine(array_column($cards, 'id'), array_column($cards, 'sizes'));
    }
}
