<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ProductStatus;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Best sellers, and slow sellers: products with stock on hand that sell little or nothing.
 */
class SellerReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Seller Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-13';

    private Product $shirt;

    private Product $belt;

    private Product $scarf;

    private Category $accessories;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        // Only this test's products: the seeded catalog (if any) stays out of the stock list.
        Product::query()->update(['is_active' => 0]);

        $managed = ['manage_stock' => true, 'low_stock_threshold' => 0];
        $this->shirt = Product::factory()->create(['name' => 'قميص', 'sku' => 'SLOW-SHIRT', ...$managed, 'stock_quantity' => 0]);
        $first = Product::factory()->variationOf($this->shirt)->create(['name' => 'قميص 1', 'sku' => 'SLOW-SHIRT-1', ...$managed, 'stock_quantity' => 5]);
        Product::factory()->variationOf($this->shirt)->create(['name' => 'قميص 2', 'sku' => 'SLOW-SHIRT-2', ...$managed, 'stock_quantity' => 0]);
        Product::factory()->variationOf($this->shirt)->create(['name' => 'قميص 3', 'sku' => 'SLOW-SHIRT-3', ...$managed, 'stock_quantity' => 3]);
        $this->belt = Product::factory()->create(['name' => 'حزام', 'sku' => 'SLOW-BELT', ...$managed, 'stock_quantity' => 20]);
        $this->scarf = Product::factory()->create(['name' => 'وشاح', 'sku' => 'SLOW-SCARF', ...$managed, 'stock_quantity' => 4]);

        Product::factory()->create(['name' => 'طقم', 'sku' => 'SLOW-SET', 'bundle_type' => 'set', ...$managed, 'stock_quantity' => 9]);
        Product::factory()->create(['name' => 'قبعة', 'sku' => 'SLOW-HAT', 'manage_stock' => false, 'stock_quantity' => 50]);
        Product::factory()->create(['name' => 'معطل', 'sku' => 'SLOW-OFF', ...$managed, 'stock_quantity' => 10, 'is_active' => 0]);
        Product::factory()->create(['name' => 'نفد', 'sku' => 'SLOW-OUT', ...$managed, 'stock_quantity' => 0]);
        Product::factory()->create(['name' => 'مسودة', 'sku' => 'SLOW-DRAFT', ...$managed, 'stock_quantity' => 7, 'status' => ProductStatus::DRAFT]);

        $this->accessories = Category::factory()->create(['name' => 'إكسسوارات']);
        $this->belt->categories()->attach($this->accessories->id);
        $this->scarf->categories()->attach($this->accessories->id);

        $this->sale(['created_at' => '2026-11-04 10:00'], [['product' => $first, 'quantity' => 5, 'price' => 100, 'name' => 'قميص']]);
        $this->sale(['created_at' => '2026-11-06 10:00'], [['product' => $this->scarf, 'quantity' => 3, 'price' => 30]]);
        $this->sale(['created_at' => '2026-09-01 10:00'], [['product' => $this->belt, 'quantity' => 2, 'price' => 50]]);
        $this->sale(['created_at' => '2026-11-07 10:00', 'status' => OrderStatus::CANCELLED], [['product' => $this->belt, 'quantity' => 1, 'price' => 50]]);
    }

    public function test_best_sellers_by_units(): void
    {
        $this->report('sellers', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Sellers/Index');
            $best = $page->toArray()['props']['best'];

            $this->assertSame(['قميص', 'وشاح'], array_column($best, 'name'));
            $this->assertSame(5, $best[0]['units']);
            $this->assertSame('500.00 ₪', $best[0]['net']);
            $page->etc();
        });
    }

    public function test_slow_sellers_are_in_stock_items_with_few_sales(): void
    {
        $rows = $this->reportProps('sellers', self::PERMISSION, self::RANGE)['table']['data'];

        $this->assertSame(['حزام', 'وشاح', 'قميص'], array_column($rows, 'name'));
        $this->assertSame([0, 3, 5], array_column($rows, 'units'));
        $this->assertSame(['حزام'], array_column($this->reportProps('sellers', self::PERMISSION, self::RANGE.'&filter[units_to]=0')['table']['data'], 'name'));
        $this->assertSame(['حزام', 'وشاح'], array_column($this->reportProps('sellers', self::PERMISSION, self::RANGE.'&filter[category]='.$this->accessories->id)['table']['data'], 'name'));
        $this->assertSame(['وشاح'], array_column($this->reportProps('sellers', self::PERMISSION, self::RANGE.'&filter[search]=وشا')['table']['data'], 'name'));
    }

    public function test_stock_on_hand_sums_the_variants(): void
    {
        $rows = collect($this->reportProps('sellers', self::PERMISSION, self::RANGE)['table']['data'])->keyBy('name');

        $this->assertSame(8, $rows['قميص']['stock']);
        $this->assertSame(2, $rows['قميص']['items_in_stock']);
        $this->assertSame('SLOW-SHIRT', $rows['قميص']['sku']);
        $this->assertSame(20, $rows['حزام']['stock']);
    }

    public function test_bundles_unmanaged_inactive_and_out_of_stock_are_left_out(): void
    {
        $names = array_column($this->reportProps('sellers', self::PERMISSION, self::RANGE)['table']['data'], 'name');

        foreach (['طقم', 'قبعة', 'معطل', 'نفد', 'مسودة'] as $name) {
            $this->assertNotContains($name, $names);
        }
    }

    public function test_last_sold_is_all_time(): void
    {
        $rows = collect($this->reportProps('sellers', self::PERMISSION, self::RANGE)['table']['data'])->keyBy('name');

        $this->assertSame('2026-09-01', $rows['حزام']['last_sold']);
        $this->assertSame('2026-11-04', $rows['قميص']['last_sold']);
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('sellers/export', self::PERMISSION, self::RANGE);

        $this->assertSame('الاسم', $rows[0][0]);
        $this->assertSame(['حزام', 'SLOW-BELT', '20', '1', '0', '0.00', '2026-09-01'], $rows[1]);
        $this->assertCount(4, $rows);
    }

    public function test_ties_are_ordered_by_stock_on_the_page_and_in_the_csv(): void
    {
        Product::factory()->create(['name' => 'جوارب', 'sku' => 'SLOW-SOCKS', 'manage_stock' => true, 'stock_quantity' => 50]);
        Product::factory()->create(['name' => 'منديل', 'sku' => 'SLOW-TISSUE', 'manage_stock' => true, 'stock_quantity' => 7]);

        $page = array_column($this->reportProps('sellers', self::PERMISSION, self::RANGE)['table']['data'], 'name');
        $csv = array_column(array_slice($this->csv('sellers/export', self::PERMISSION, self::RANGE), 1), 0);

        $this->assertSame(['جوارب', 'حزام', 'منديل', 'وشاح', 'قميص'], $page);
        $this->assertSame($page, $csv);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('sellers', 'Sales Report View')->assertForbidden();
    }
}
