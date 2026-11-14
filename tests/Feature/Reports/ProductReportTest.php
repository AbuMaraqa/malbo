<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\ReturnRequestType;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Sales by product, category, colour, size and set/bundle, read from the order lines.
 */
class ProductReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Product Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-13';

    private Product $shirt;

    private Product $trousers;

    private Product $set;

    private Category $clothes;

    private Category $shirts;

    private Category $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        app()->setLocale('ar');

        Attribute::query()->where('is_size', true)->update(['is_size' => false]);
        Attribute::factory()->create(['name' => 'لون التقرير', 'type' => AttributeType::COLOR->value]);
        Attribute::factory()->create(['name' => 'مقاس التقرير', 'is_size' => true]);

        $this->clothes = Category::factory()->create(['name' => 'ملابس']);
        $this->shirts = Category::factory()->create(['name' => 'قمصان', 'parent_id' => $this->clothes->id]);
        $this->sale = Category::factory()->create(['name' => 'تخفيضات']);

        $this->shirt = Product::factory()->create(['name' => 'قميص كتان', 'sku' => 'SHIRT-'.Str::random(4)]);
        $this->shirt->categories()->attach($this->shirts->id);
        $this->trousers = Product::factory()->create(['name' => 'بنطال', 'sku' => 'TR-'.Str::random(4)]);
        $this->trousers->categories()->attach([$this->clothes->id, $this->sale->id]);
        $this->set = Product::factory()->create(['name' => 'طقم صيفي', 'sku' => 'SET-'.Str::random(4), 'bundle_type' => 'set']);

        $blackM = Product::factory()->variationOf($this->shirt)->create(['name' => 'قميص أسود M', 'sku' => 'SH-BM-'.Str::random(4)]);
        $whiteL = Product::factory()->variationOf($this->shirt)->create(['name' => 'قميص أبيض L', 'sku' => 'SH-WL-'.Str::random(4)]);
        $black = [self::option('لون التقرير', 'أسود', null, 'Black'), self::option('مقاس التقرير', 'M')];
        $white = [self::option('لون التقرير', 'أبيض', null, 'White'), self::option('مقاس التقرير', 'L')];

        $first = $this->sale(['created_at' => '2026-11-03 10:00', 'discount' => 28], [
            ['product' => $blackM, 'quantity' => 2, 'price' => 100, 'options' => $black, 'name' => 'قميص كتان'],
            ['product' => $this->trousers, 'quantity' => 1, 'price' => 80],
        ]);
        $this->request($first, ReturnRequestType::RETURN, [$first->items[0]->id => 1]);

        $bundle = ['product' => $this->set, 'group' => (string) Str::uuid(), 'quantity' => 1, 'name' => 'طقم صيفي'];
        $this->sale(['created_at' => '2026-11-05 10:00'], [
            ['product' => $whiteL, 'quantity' => 1, 'price' => 100, 'options' => $white, 'name' => 'قميص كتان'],
            ['product' => $blackM, 'quantity' => 1, 'price' => 60, 'options' => $black, 'name' => 'قميص كتان', 'bundle' => $bundle],
            ['product' => $this->trousers, 'quantity' => 1, 'price' => 40, 'bundle' => $bundle],
        ]);

        $this->sale(['created_at' => '2026-11-07 10:00', 'channel' => OrderChannel::B2B], [['product' => $this->trousers, 'quantity' => 10, 'price' => 50]]);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array<string, mixed>>
     */
    private function rows(string $query = ''): \Illuminate\Support\Collection
    {
        return collect($this->reportProps('products', self::PERMISSION, self::RANGE.$query)['table']['data'])->keyBy('name');
    }

    public function test_variants_roll_up_into_their_product(): void
    {
        $this->report('products', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Products/Index');
            $props = $page->toArray()['props'];
            $rows = collect($props['table']['data'])->keyBy('name');

            $this->assertSame(['بنطال', 'قميص كتان'], $rows->keys()->all()); // by net, highest first
            $this->assertSame(4, $rows['قميص كتان']['units']);
            $this->assertSame(2, $rows['قميص كتان']['orders']);
            $this->assertSame('360.00 ₪', $rows['قميص كتان']['gross']);
            $this->assertSame('20.00 ₪', $rows['قميص كتان']['discounts']);
            $this->assertSame('90.00 ₪', $rows['قميص كتان']['returns']);
            $this->assertSame('250.00 ₪', $rows['قميص كتان']['net']);
            $this->assertSame(1, $rows['قميص كتان']['returned_units']);
            $this->assertSame($this->shirt->sku, $rows['قميص كتان']['sku']);
            $this->assertSame(12, $rows['بنطال']['units']);
            $this->assertSame('612.00 ₪', $rows['بنطال']['net']);
            $this->assertSame('862.00 ₪', $props['totals']['net']);
            $this->assertSame(16, $props['totals']['units']);
            $this->assertSame(['بنطال', 'قميص كتان'], $props['chart']['labels']);
            $page->etc();
        });
    }

    public function test_bundle_pieces_count_under_their_own_product_colour_and_size(): void
    {
        $colours = $this->rows('&filter[by]=color');
        $this->assertSame(3, $colours['أسود']['units']);
        $this->assertSame('260.00 ₪', $colours['أسود']['gross']);
        $this->assertSame(1, $colours['أبيض']['units']);

        $sizes = $this->rows('&filter[by]=size');
        $this->assertSame(3, $sizes['M']['units']);
        $this->assertSame(1, $sizes['L']['units']);
    }

    public function test_bundles_have_their_own_rows(): void
    {
        $rows = $this->rows('&filter[by]=bundle');

        $this->assertSame(['طقم صيفي'], $rows->keys()->all());
        $this->assertSame(1, $rows['طقم صيفي']['bundles']);
        $this->assertSame(2, $rows['طقم صيفي']['units']);
        $this->assertSame('100.00 ₪', $rows['طقم صيفي']['gross']);
        $this->assertSame(1, $rows['طقم صيفي']['orders']);
    }

    public function test_colour_and_size_come_from_the_snapshot(): void
    {
        $this->shirt->attributeValues()->detach();

        app()->setLocale('en');
        $colours = collect($this->actingAs($this->reportStaff([self::PERMISSION]))->get($this->siteUrl('/en/admin/reports/products'.self::RANGE.'&filter[by]=color'))->assertOk()->viewData('page')['props']['table']['data'])->keyBy('name');

        $this->assertSame(3, $colours['Black']['units']);
        $this->assertSame(1, $colours['White']['units']);
    }

    public function test_lines_without_the_option_are_not_set(): void
    {
        $sizes = $this->rows('&filter[by]=size');

        $this->assertSame(12, $sizes['غير محدد']['units']);
        $this->assertSame('612.00 ₪', $sizes['غير محدد']['net']);
    }

    public function test_a_product_in_two_categories_counts_in_each_but_once_in_totals(): void
    {
        $props = $this->reportProps('products', self::PERMISSION, self::RANGE.'&filter[by]=category');
        $rows = collect($props['table']['data'])->keyBy('name');

        $this->assertSame('612.00 ₪', $rows['ملابس']['net']);
        $this->assertSame('612.00 ₪', $rows['تخفيضات']['net']);
        $this->assertSame('250.00 ₪', $rows['قمصان']['net']);
        $this->assertSame('862.00 ₪', $props['totals']['net']);
    }

    public function test_the_category_filter_includes_subcategories(): void
    {
        $clothes = $this->reportProps('products', self::PERMISSION, self::RANGE.'&filter[category]='.$this->clothes->id);
        $this->assertEqualsCanonicalizing(['قميص كتان', 'بنطال'], array_column($clothes['table']['data'], 'name'));
        $this->assertSame('862.00 ₪', $clothes['totals']['net']);

        $shirts = $this->reportProps('products', self::PERMISSION, self::RANGE.'&filter[category]='.$this->shirts->id);
        $this->assertSame(['قميص كتان'], array_column($shirts['table']['data'], 'name'));
        $this->assertSame('250.00 ₪', $shirts['totals']['net']);

        $byCategory = $this->reportProps('products', self::PERMISSION, self::RANGE.'&filter[by]=category&filter[category]='.$this->shirts->id);
        $this->assertSame(['قمصان'], array_column($byCategory['table']['data'], 'name'));
    }

    public function test_search_sort_and_units_range(): void
    {
        $this->assertSame(['قميص كتان'], $this->rows('&filter[search]=قميص')->keys()->all());
        $this->assertSame(['بنطال'], $this->rows('&filter[units_from]=5')->keys()->all());
        $this->assertSame(['قميص كتان', 'بنطال'], $this->rows('&sort=units')->keys()->all());
        $this->assertSame(['بنطال'], $this->rows('&filter[channel]=b2b')->keys()->all());
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('products/export', self::PERMISSION, self::RANGE.'&filter[search]=قميص');

        $this->assertSame('الاسم', $rows[0][0]);
        $this->assertCount(2, $rows);
        $this->assertSame(['قميص كتان', $this->shirt->sku, '2', '4', '360.00', '20.00', '90.00', '250.00', '1'], $rows[1]);

        $bundles = $this->csv('products/export', self::PERMISSION, self::RANGE.'&filter[by]=bundle');
        $this->assertSame('طقم صيفي', $bundles[1][0]);
    }

    public function test_pages_never_share_rows_when_sorted_by_ties(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $product = Product::factory()->create(['name' => 'تعادل '.$i, 'sku' => 'TIE-'.$i.'-'.Str::random(4)]);
            $this->sale(['created_at' => '2026-11-09 10:00'], [['product' => $product, 'quantity' => 1, 'price' => 10]]);
        }

        $ids = fn (int $page) => array_column($this->reportProps('products', self::PERMISSION, self::RANGE.'&sort=units&perPage=10&page='.$page)['table']['data'], 'id');
        [$first, $second, $third] = [$ids(1), $ids(2), $ids(3)];

        $this->assertSame([], array_intersect($first, $second));
        $this->assertSame([], array_intersect($second, $third));
        $this->assertCount(30, array_unique([...$first, ...$second, ...$third]));
    }

    public function test_a_search_with_a_comma(): void
    {
        $this->assertSame([], $this->rows('&filter[search]=Shirt, red')->keys()->all());
        $this->assertCount(1, $this->csv('products/export', self::PERMISSION, self::RANGE.'&filter[search]=Shirt, red'));
    }

    public function test_bundles_filtered_by_category_are_whole(): void
    {
        $this->set->categories()->attach($this->sale->id);

        $rows = $this->rows('&filter[by]=bundle&filter[category]='.$this->sale->id);

        $this->assertSame(['طقم صيفي'], $rows->keys()->all());
        $this->assertSame(2, $rows['طقم صيفي']['units']);
        $this->assertSame('100.00 ₪', $rows['طقم صيفي']['gross']);

        $this->assertSame([], $this->rows('&filter[by]=bundle&filter[category]='.$this->shirts->id)->keys()->all());
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('products', 'Sales Report View')->assertForbidden();
    }
}
