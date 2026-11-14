<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Carbon;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Services\PricingService;

/**
 * The shop's filters: filterable attributes (OR inside one, AND across them on the same variant),
 * the displayed price, facet counts, all kept in the URL.
 */
class ShopFiltersTest extends StorefrontTestCase
{
    private const SHOP = 'Store/Storefront/Shop';

    private Attribute $color;

    private Attribute $size;

    private AttributeValue $black;

    private AttributeValue $white;

    private AttributeValue $red;

    private AttributeValue $m;

    private AttributeValue $l;

    protected function setUp(): void
    {
        parent::setUp();

        $this->color = $this->attribute('اللون', AttributeType::COLOR);
        $this->black = $this->value($this->color, 'أسود', ['color_code' => '#000000']);
        $this->white = $this->value($this->color, 'أبيض', ['color_code' => '#ffffff']);
        $this->red = $this->value($this->color, 'أحمر', ['color_code' => '#ff0000']);
        $this->size = $this->attribute('المقاس');
        $this->m = $this->value($this->size, 'M');
        $this->l = $this->value($this->size, 'L');
    }

    public function test_values_of_one_attribute_are_or(): void
    {
        $black = $this->productWith([[$this->black]]);
        $white = $this->productWith([[$this->white]]);
        $this->productWith([[$this->red]]);

        $this->assertEqualsCanonicalizing([$black->id, $white->id], $this->ids($this->attr($this->color, $this->black, $this->white)));
        $this->assertSame([$black->id], $this->ids($this->attr($this->color, $this->black)));
    }

    public function test_attributes_are_and_on_the_same_variant(): void
    {
        $this->productWith([[$this->black, $this->m], [$this->white, $this->l]]);
        $blackL = $this->productWith([[$this->black, $this->l]]);

        $this->assertSame([$blackL->id], $this->ids($this->attr($this->color, $this->black).'&'.$this->attr($this->size, $this->l)));
    }

    public function test_hidden_inactive_or_unorderable_variants_do_not_match(): void
    {
        $hiddenValue = $this->value($this->size, 'XS', ['is_active' => false]);

        $this->productWith([[$this->black]], ['is_active' => 0]);
        $this->productWith([[$this->black]], ['stock_quantity' => 0]);
        $this->productWith([[$this->black, $hiddenValue]]);
        $this->productWith([[$this->black]], ['manage_stock' => false, 'stock_status' => 'out_of_stock']);
        $deleted = $this->productWith([[$this->black]]);
        $deleted->variations()->first()->delete();
        $fine = $this->productWith([[$this->black]]);

        $this->assertSame([$fine->id], $this->ids($this->attr($this->color, $this->black)));
    }

    public function test_backorder_and_unmanaged_variants_match(): void
    {
        $backorder = $this->productWith([[$this->black]], ['stock_quantity' => 0, 'allow_backorder' => true]);
        $unmanaged = $this->productWith([[$this->black]], ['manage_stock' => false, 'stock_quantity' => 0]);

        $this->assertEqualsCanonicalizing([$backorder->id, $unmanaged->id], $this->ids($this->attr($this->color, $this->black)));
    }

    public function test_only_filterable_attributes_and_their_values_count(): void
    {
        $material = $this->attribute('الخامة', filterable: false);
        $cotton = $this->value($material, 'قطن');
        $one = $this->productWith([[$this->black, $cotton]]);
        $two = $this->productWith([[$this->white]]);

        // Not filterable, or a value of another attribute: ignored.
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $this->ids($this->attr($material, $cotton)));
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $this->ids('attr['.$this->color->id.']='.$this->m->id));

        $facets = $this->props('/ar/shop', self::SHOP)['facets']['attributes'];
        $this->assertSame([$this->color->id], array_column($facets, 'id'));
        $this->assertTrue($facets[0]['swatch']);
        $this->assertSame(['أسود', 'أبيض'], array_column($facets[0]['values'], 'name'));
        $this->assertSame('#000000', $facets[0]['values'][0]['color']);
    }

    public function test_facet_counts_follow_the_other_filters(): void
    {
        $this->productWith([[$this->black, $this->m], [$this->white, $this->l]]);
        $this->productWith([[$this->black, $this->l]]);
        $this->productWith([[$this->white, $this->m]]);

        $this->assertSame([$this->black->id => 2, $this->white->id => 2, $this->m->id => 2, $this->l->id => 2], $this->counts(''));

        // Size L: colors count variants in L; sizes still count every size (OR inside one attribute).
        $this->assertSame([$this->black->id => 1, $this->white->id => 1, $this->m->id => 2, $this->l->id => 2], $this->counts($this->attr($this->size, $this->l)));

        $this->assertSame([$this->black->id => 2, $this->white->id => 2, $this->m->id => 1, $this->l->id => 1], $this->counts($this->attr($this->color, $this->black)));
    }

    public function test_values_without_matches_are_hidden_unless_selected(): void
    {
        $this->productWith([[$this->black, $this->m]]);

        $colors = $this->facet('', $this->color);
        $this->assertSame([$this->black->id], array_column($colors['values'], 'id'));

        $colors = $this->facet($this->attr($this->color, $this->red), $this->color);
        $this->assertSame([$this->black->id, $this->red->id], array_column($colors['values'], 'id'));
        $this->assertSame([false, true], array_column($colors['values'], 'selected'));
        $this->assertSame([1, 0], array_column($colors['values'], 'count'));
    }

    public function test_price_range_uses_the_displayed_price(): void
    {
        Carbon::setTestNow('2026-11-11 12:00:00');

        $plain = $this->simple(['base_price' => 100]);
        $sale = $this->simple(['base_price' => 200, 'sale_price' => 150]);
        $open = $this->simple(['base_price' => 300, 'discount_type' => 'percentage', 'discount_value' => 50, 'discount_start_date' => '2026-11-01', 'discount_end_date' => '2026-11-30']);
        $closed = $this->simple(['base_price' => 140, 'discount_type' => 'percentage', 'discount_value' => 50, 'discount_end_date' => '2026-11-10']);
        $fixed = $this->simple(['base_price' => 400, 'discount_type' => 'fixed', 'discount_value' => 300]);

        $props = $this->props('/ar/shop?price_min=110&price_max=160&sort=price_asc', self::SHOP);

        // Equal prices: the newest first.
        $this->assertSame([$closed->id, $open->id, $sale->id], array_column($props['products']['data'], 'id'));
        $this->assertSame(['140.00 ₪', '150.00 ₪', '150.00 ₪'], array_column(array_column($props['products']['data'], 'price'), 'label'));
        $this->assertEquals(['min' => 100, 'max' => 150], $props['facets']['price']);
        $this->assertSame(110.0, (float) $props['filters']['price_min']);

        // Reversed bounds are put in order.
        $this->assertEqualsCanonicalizing([$plain->id, $fixed->id], $this->ids('price_min=105&price_max=95'));

        Carbon::setTestNow();
    }

    public function test_the_sql_price_matches_pricing_service(): void
    {
        Carbon::setTestNow('2026-11-11 15:30:00');

        $cases = [
            ['base_price' => 99.99],
            ['base_price' => 99.99, 'sale_price' => 0],
            ['base_price' => 80, 'sale_price' => 90],
            ['base_price' => 99.99, 'sale_price' => 89.99, 'discount_type' => 'percentage', 'discount_value' => 15],
            ['base_price' => 50, 'discount_type' => 'percentage', 'discount_value' => 150],
            ['base_price' => 50, 'discount_type' => 'fixed', 'discount_value' => 80],
            ['base_price' => 77.77, 'discount_type' => 'fixed', 'discount_value' => 7.5, 'discount_end_date' => '2026-11-11 00:00:00'],
            ['base_price' => 77.77, 'discount_type' => 'fixed', 'discount_value' => 7.5, 'discount_end_date' => '2026-11-11 15:00:00'],
            ['base_price' => 77.77, 'discount_type' => 'percentage', 'discount_value' => 10, 'discount_start_date' => '2026-11-11 16:00:00'],
            ['base_price' => 77.77, 'discount_type' => 'percentage', 'discount_value' => 10, 'discount_start_date' => '2026-11-11 15:30:00'],
            ['base_price' => 60, 'discount_type' => 'none', 'discount_value' => 10],
        ];

        $pricing = app(PricingService::class);
        $repository = app(ProductRepositoryInterface::class);

        foreach ($cases as $index => $columns) {
            $product = $this->simple($columns);
            $amount = $pricing->for($product->fresh())->amount;

            $found = collect($repository->storefrontListing(['price_min' => $amount, 'price_max' => $amount])->items())->pluck('id');
            $this->assertContains($product->id, $found, "case {$index}: {$amount}");
        }

        Carbon::setTestNow();
    }

    public function test_filters_survive_pagination_and_sort(): void
    {
        foreach (range(1, 25) as $i) {
            $this->productWith([[$this->black]], [], ['base_price' => $i]);
        }

        $query = $this->attr($this->color, $this->black).'&price_max=500&sort=price_asc';
        $props = $this->props('/ar/shop?'.$query, self::SHOP);

        $this->assertSame(25, $props['products']['total']);
        $next = urldecode((string) $props['products']['next_url']);
        $this->assertStringContainsString('attr['.$this->color->id.']='.$this->black->id, $next);
        $this->assertStringContainsString('sort=price_asc', $next);
        $this->assertStringContainsString('price_max=500', $next);
        $this->assertSame([$this->color->id => [$this->black->id]], $props['filters']['attr']);

        $this->assertCount(1, $this->props('/ar/shop?'.$query.'&page=2', self::SHOP)['products']['data']);
    }

    public function test_malformed_filters_are_ignored(): void
    {
        $product = $this->productWith([[$this->black]]);

        foreach (['attr=x', 'attr[]=1', 'attr[abc]=1', 'attr['.$this->color->id.'][]=x', 'attr[999999]=1', 'attr['.$this->color->id.']=abc,,-1', 'price_min=abc&price_max=-5', 'price_min[]=1', 'attr['.$this->color->id.']=999999'] as $query) {
            $props = $this->props('/ar/shop?'.$query, self::SHOP);
            $this->assertSame([$product->id], array_column($props['products']['data'], 'id'), $query);
        }
    }

    public function test_category_filter_still_applies_with_facets(): void
    {
        $category = Category::factory()->create(['slug' => 'shirts']);
        $inside = $this->productWith([[$this->black]]);
        $inside->categories()->attach($category->id);
        $this->productWith([[$this->black]]);

        $props = $this->props('/ar/category/shirts?'.$this->attr($this->color, $this->black), 'Store/Storefront/Category');

        $this->assertSame([$inside->id], array_column($props['products']['data'], 'id'));
        $this->assertSame(1, $props['facets']['attributes'][0]['values'][0]['count']);
    }

    private function attribute(string $name, AttributeType $type = AttributeType::SELECT, bool $filterable = true): Attribute
    {
        return Attribute::factory()->create(['name' => $name, 'type' => $type->value, 'is_filterable' => $filterable]);
    }

    private function value(Attribute $attribute, string $name, array $columns = []): AttributeValue
    {
        return AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => $name, 'sort_order' => AttributeValue::query()->where('attribute_id', $attribute->id)->count(), 'is_active' => true, ...$columns]);
    }

    /**
     * A product whose variants carry these values; each variant managed with 5 pieces unless $variant says otherwise.
     *
     * @param  list<list<AttributeValue>>  $variants
     */
    private function productWith(array $variants, array $variant = [], array $parent = []): Product
    {
        $product = Product::factory()->create($parent);

        foreach ($variants as $values) {
            $row = Product::factory()->variationOf($product)->create(['manage_stock' => true, 'stock_quantity' => 5, ...$variant]);
            $row->attributeValues()->attach(array_map(fn (AttributeValue $value) => $value->id, $values));
        }

        return $product;
    }

    private function simple(array $columns): Product
    {
        return Product::factory()->create(['sale_price' => null, ...$columns]);
    }

    private function attr(Attribute $attribute, AttributeValue ...$values): string
    {
        return 'attr['.$attribute->id.']='.implode(',', array_map(fn (AttributeValue $value) => $value->id, $values));
    }

    /**
     * @return list<int>
     */
    private function ids(string $query): array
    {
        return array_column($this->props('/ar/shop?'.$query, self::SHOP)['products']['data'], 'id');
    }

    /**
     * @return array<int, int> value id => count, in facet order
     */
    private function counts(string $query): array
    {
        $counts = [];

        foreach ($this->props('/ar/shop?'.$query, self::SHOP)['facets']['attributes'] as $attribute) {
            foreach ($attribute['values'] as $value) {
                $counts[$value['id']] = $value['count'];
            }
        }

        return $counts;
    }

    private function facet(string $query, Attribute $attribute): array
    {
        return collect($this->props('/ar/shop?'.$query, self::SHOP)['facets']['attributes'])->firstWhere('id', $attribute->id);
    }
}
