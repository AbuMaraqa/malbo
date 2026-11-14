<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Return rate: units returned over units sold (orders of the period), exchanges apart, and why.
 */
class ReturnReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Return Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-13';

    private Category $tops;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));

        $shirt = Product::factory()->create(['name' => 'قميص']);
        $trousers = Product::factory()->create(['name' => 'بنطال']);
        $belt = Product::factory()->create(['name' => 'حزام']);
        $this->tops = Category::factory()->create(['name' => 'علوي']);
        $shirt->categories()->attach($this->tops->id);

        $first = $this->sale(['created_at' => '2026-11-03 10:00'], [
            ['product' => $shirt, 'quantity' => 4, 'price' => 100],
            ['product' => $trousers, 'quantity' => 2, 'price' => 50],
        ]);
        $this->request($first, ReturnRequestType::RETURN, [$first->items[0]->id => 1], ReturnReason::WRONG_SIZE);
        $this->request($first, ReturnRequestType::EXCHANGE, [$first->items[0]->id => 1], ReturnReason::DEFECTIVE);
        $this->request($first, ReturnRequestType::RETURN, [$first->items[1]->id => 1], ReturnReason::CHANGED_MIND, ReturnRequestStatus::REQUESTED);
        $this->request($first, ReturnRequestType::RETURN, [$first->items[1]->id => 1], ReturnReason::CHANGED_MIND, ReturnRequestStatus::REJECTED);

        $this->sale(['created_at' => '2026-11-04 10:00', 'status' => OrderStatus::RETURNED], [['product' => $belt, 'quantity' => 2, 'price' => 30]]);
        $this->sale(['created_at' => '2026-11-05 10:00'], [['product' => $trousers, 'quantity' => 4, 'price' => 50]]);
        // Another period: not counted.
        $old = $this->sale(['created_at' => '2026-10-05 10:00'], [['product' => $shirt, 'quantity' => 1, 'price' => 100]]);
        $this->request($old, ReturnRequestType::RETURN, [$old->items[0]->id => 1]);
    }

    public function test_rate_is_returned_over_sold_units(): void
    {
        $this->report('returns', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Returns/Index');
            $summary = $page->toArray()['props']['summary'];

            $this->assertSame(12, $summary['sold']);
            $this->assertSame(3, $summary['returned']);
            $this->assertEquals(25.0, $summary['return_rate']);
            $this->assertSame('160.00 ₪', $summary['returns']);
            $this->assertSame(2, $summary['requests']);
            $page->etc();
        });
    }

    public function test_exchanges_are_their_own_rate(): void
    {
        $summary = $this->reportProps('returns', self::PERMISSION, self::RANGE)['summary'];

        $this->assertSame(1, $summary['exchanged']);
        $this->assertEquals(8.3, $summary['exchange_rate']);
    }

    public function test_reasons_include_whole_order_returns(): void
    {
        $reasons = collect($this->reportProps('returns', self::PERMISSION, self::RANGE)['reasons'])->keyBy('key');

        $this->assertSame(1, $reasons['return:wrong_size']['units']);
        $this->assertSame(1, $reasons['exchange:defective']['units']);
        $this->assertSame(2, $reasons['order_returned']['units']);
        $this->assertCount(3, $reasons);
        $this->assertSame('المقاس غير مناسب', $reasons['return:wrong_size']['reason']);
    }

    public function test_rejected_and_open_requests_do_not_count(): void
    {
        $rows = collect($this->reportProps('returns', self::PERMISSION, self::RANGE)['table']['data'])->keyBy('name');

        $this->assertSame(0, $rows['بنطال']['returned']);
        $this->assertSame(6, $rows['بنطال']['sold']);
        $this->assertSame(1, $rows['قميص']['returned']);
        $this->assertSame(1, $rows['قميص']['exchanged']);
        $this->assertEquals(25.0, $rows['قميص']['rate']);
        $this->assertEquals(100.0, $rows['حزام']['rate']);
        $this->assertSame(['حزام', 'قميص', 'بنطال'], $rows->keys()->all());
    }

    public function test_by_category(): void
    {
        $rows = collect($this->reportProps('returns', self::PERMISSION, self::RANGE.'&filter[by]=category')['table']['data'])->keyBy('name');

        $this->assertSame(4, $rows['علوي']['sold']);
        $this->assertSame(1, $rows['علوي']['returned']);
        $this->assertSame(8, $rows['غير محدد']['sold']);

        $only = $this->reportProps('returns', self::PERMISSION, self::RANGE.'&filter[category]='.$this->tops->id)['summary'];
        $this->assertSame(4, $only['sold']);
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('returns/export', self::PERMISSION, self::RANGE.'&filter[sold_from]=3');

        $this->assertSame('الاسم', $rows[0][0]);
        $this->assertSame(['قميص', '4', '1', '1', '100.00', '25.0'], $rows[1]);
        $this->assertCount(3, $rows);
    }

    public function test_reasons_add_up_to_the_returned_and_exchanged_units(): void
    {
        // One piece exchanged, then the whole order sent back: all of it counts as returned.
        $order = $this->sale(['created_at' => '2026-11-06 10:00', 'status' => OrderStatus::RETURNED], [['product' => Product::factory()->create(['name' => 'وشاح']), 'quantity' => 2, 'price' => 20]]);
        $this->request($order, ReturnRequestType::EXCHANGE, [$order->items[0]->id => 1], ReturnReason::DEFECTIVE);

        $props = $this->reportProps('returns', self::PERMISSION, self::RANGE);
        $byType = collect($props['reasons'])->groupBy('type')->map(fn ($rows) => $rows->sum('units'));

        $this->assertSame(5, $props['summary']['returned']);
        $this->assertSame(1, $props['summary']['exchanged']);
        $this->assertSame($props['summary']['returned'], $byType['return']);
        $this->assertSame($props['summary']['exchanged'], $byType['exchange']);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('returns', 'Sales Report View')->assertForbidden();
    }
}
