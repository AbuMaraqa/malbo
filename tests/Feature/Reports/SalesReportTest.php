<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Daily and monthly sales: what counts as a sale, its money, its days.
 */
class SalesReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Sales Report View';

    private const NOVEMBER = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-13';

    protected function setUp(): void
    {
        parent::setUp();

        config(['reports.timezone' => 'Asia/Hebron']);
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
    }

    private function summary(string $query = self::NOVEMBER): array
    {
        return $this->reportProps('sales', self::PERMISSION, $query)['summary'];
    }

    public function test_only_confirmed_orders_count(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->sale(['status' => $status, 'created_at' => '2026-11-10 10:00'], [['quantity' => 1, 'price' => 100]]);
        }
        $this->sale(['created_at' => '2026-11-10 10:00'], [['quantity' => 1, 'price' => 100]])->delete();
        $this->sale(['created_at' => '2026-10-31 10:00'], [['quantity' => 1, 'price' => 100]]);

        $props = $this->reportProps('sales', self::PERMISSION, self::NOVEMBER);

        $this->assertSame(7, $props['summary']['orders']);
        $this->assertSame('700.00 ₪', $props['summary']['gross']);
        $this->assertSame('100.00 ₪', $props['summary']['returns']);
        $this->assertSame('600.00 ₪', $props['summary']['net']);
        $this->assertSame(2, $props['pending']['orders']);
        $this->assertSame('200.00 ₪', $props['pending']['value']);
    }

    public function test_order_money_is_counted_once_whatever_the_lines(): void
    {
        $this->sale(['created_at' => '2026-11-05 10:00', 'discount' => 30, 'delivery_fee' => 20], [
            ['quantity' => 1, 'price' => 100], ['quantity' => 2, 'price' => 50], ['quantity' => 4, 'price' => 25],
        ]);

        $summary = $this->summary();

        $this->assertSame(1, $summary['orders']);
        $this->assertSame(7, $summary['units']);
        $this->assertSame('300.00 ₪', $summary['gross']);
        $this->assertSame('30.00 ₪', $summary['discounts']);
        $this->assertSame('20.00 ₪', $summary['delivery']);
        $this->assertSame('270.00 ₪', $summary['net']);
        $this->assertSame('290.00 ₪', $summary['total']);
        $this->assertSame('270.00 ₪', $summary['aov']);
    }

    public function test_net_subtracts_discounts_and_returns_at_the_discounted_line_price(): void
    {
        $order = $this->sale(['created_at' => '2026-11-05 10:00', 'discount' => 30], [['quantity' => 2, 'price' => 100], ['quantity' => 1, 'price' => 100]]);
        [$a, $b] = [$order->items[0], $order->items[1]];
        $this->request($order, ReturnRequestType::RETURN, [$a->id => 1]);
        $this->request($order, ReturnRequestType::RETURN, [$b->id => 1], status: ReturnRequestStatus::REQUESTED);
        $this->request($order, ReturnRequestType::RETURN, [$b->id => 1], status: ReturnRequestStatus::REJECTED);

        $summary = $this->summary();

        $this->assertSame('90.00 ₪', $summary['returns']);
        $this->assertSame('180.00 ₪', $summary['net']);
        $this->assertSame(1, $summary['returned_units']);
    }

    public function test_a_returned_order_nets_to_zero(): void
    {
        $this->sale(['created_at' => '2026-11-05 10:00', 'discount' => 10, 'delivery_fee' => 15, 'status' => OrderStatus::RETURNED], [
            ['quantity' => 1, 'price' => 10], ['quantity' => 1, 'price' => 10], ['quantity' => 1, 'price' => 10],
        ]);

        $summary = $this->summary();

        $this->assertSame('20.00 ₪', $summary['returns']);
        $this->assertSame('0.00 ₪', $summary['net']);
        // Refused at the door: an order, but no delivery fee earned.
        $this->assertSame(1, $summary['orders']);
        $this->assertSame('0.00 ₪', $summary['delivery']);
        $this->assertSame('0.00 ₪', $summary['total']);
    }

    public function test_a_delivered_then_returned_order_keeps_its_delivery_fee(): void
    {
        $order = $this->sale(['created_at' => '2026-11-05 10:00', 'delivery_fee' => 15, 'status' => OrderStatus::RETURNED], [['quantity' => 1, 'price' => 10]]);
        $order->statusHistory()->create(['status' => OrderStatus::DELIVERED->value]);

        $summary = $this->summary();

        $this->assertSame('15.00 ₪', $summary['delivery']);
        $this->assertSame('15.00 ₪', $summary['total']);
    }

    public function test_the_comparison_period_is_shown(): void
    {
        $props = $this->reportProps('sales', self::PERMISSION, '?filter[preset]=this_month');

        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-13'], $props['compare']);
        $this->assertSame(['from' => '2026-11-03', 'to' => '2026-11-12'], $this->reportProps('sales', self::PERMISSION, '?filter[date_from]=2026-11-13&filter[date_to]=2026-11-22')['compare']);
    }

    public function test_exchanges_do_not_reduce_sales(): void
    {
        $order = $this->sale(['created_at' => '2026-11-05 10:00', 'status' => OrderStatus::EXCHANGED], [['quantity' => 1, 'price' => 80]]);
        $this->request($order, ReturnRequestType::EXCHANGE, [$order->items[0]->id => 1], ReturnReason::WRONG_SIZE);

        $summary = $this->summary();

        $this->assertSame('0.00 ₪', $summary['returns']);
        $this->assertSame('80.00 ₪', $summary['net']);
    }

    public function test_money_is_exact(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->sale(['created_at' => '2026-11-05 10:00'], [['quantity' => 1, 'price' => 0.10], ['quantity' => 1, 'price' => 0.20]]);
        }
        $order = $this->sale(['created_at' => '2026-11-06 10:00', 'discount' => 10], [
            ['quantity' => 1, 'price' => 10], ['quantity' => 1, 'price' => 10], ['quantity' => 1, 'price' => 10],
        ]);
        $this->request($order, ReturnRequestType::RETURN, [$order->items[0]->id => 1]);

        $summary = $this->summary();

        $this->assertSame('33.00 ₪', $summary['gross']);
        $this->assertSame('10.00 ₪', $summary['discounts']);
        $this->assertSame('6.67 ₪', $summary['returns']);
        $this->assertSame('16.33 ₪', $summary['net']);
    }

    public function test_days_follow_the_reports_timezone(): void
    {
        $this->sale(['created_at' => '2026-11-12 21:30:00'], [['quantity' => 1, 'price' => 10]]); // 23:30 on the 12th there
        $this->sale(['created_at' => '2026-11-12 22:30:00'], [['quantity' => 1, 'price' => 20]]); // 00:30 on the 13th there

        $this->assertSame(1, $this->summary('?filter[preset]=today')['orders']);
        $this->assertSame('20.00 ₪', $this->summary('?filter[preset]=today')['net']);

        $chart = $this->reportProps('sales', self::PERMISSION, '?filter[date_from]=2026-11-11&filter[date_to]=2026-11-13')['chart'];
        $this->assertSame(['2026-11-11', '2026-11-12', '2026-11-13'], $chart['keys']);
        $this->assertSame([0, 1, 1], $chart['orders']);
        $this->assertEquals([0, 10, 20], $chart['net']);
    }

    public function test_the_channel_and_branch_filters(): void
    {
        $branch = Branch::factory()->create();
        $this->sale(['created_at' => '2026-11-05 10:00'], [['quantity' => 1, 'price' => 10]]);
        $this->sale(['created_at' => '2026-11-05 10:00', 'channel' => OrderChannel::B2B], [['quantity' => 1, 'price' => 20]]);
        $this->sale(['created_at' => '2026-11-05 10:00', 'branch_id' => $branch->id], [['quantity' => 1, 'price' => 40]]);

        $this->assertSame('70.00 ₪', $this->summary()['net']);
        $this->assertSame('20.00 ₪', $this->summary(self::NOVEMBER.'&filter[channel]=b2b')['net']);
        $this->assertSame('50.00 ₪', $this->summary(self::NOVEMBER.'&filter[channel]=b2c')['net']);
        $this->assertSame('40.00 ₪', $this->summary(self::NOVEMBER.'&filter[branch]='.$branch->id)['net']);
        $this->assertSame('70.00 ₪', $this->summary(self::NOVEMBER.'&filter[channel]=nonsense')['net']);
    }

    public function test_the_page_props_and_table(): void
    {
        $this->sale(['created_at' => '2026-11-02 10:00', 'delivery_fee' => 10], [['quantity' => 2, 'price' => 50]]);
        $this->sale(['created_at' => '2026-11-04 10:00'], [['quantity' => 1, 'price' => 30]]);
        $this->sale(['created_at' => '2026-11-04 11:00'], [['quantity' => 1, 'price' => 20]]);
        $this->sale(['created_at' => '2026-10-25 10:00'], [['quantity' => 1, 'price' => 100]]);

        $this->report('sales', self::PERMISSION, '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-10')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $props = $page->toArray()['props'];

                $page->component('Reports/Sales/Index');
                $this->assertSame(['2026-11-04', '2026-11-02'], array_column($props['table']['data'], 'bucket'));
                $this->assertSame(2, $props['table']['data'][0]['orders']);
                $this->assertSame('50.00 ₪', $props['table']['data'][0]['net']);
                $this->assertSame('10.00 ₪', $props['table']['data'][1]['delivery']);
                $this->assertCount(10, $props['chart']['keys']);
                $this->assertSame('custom', $props['filters']['preset']);
                $this->assertSame('day', $props['filters']['interval']);
                // The 10 days before: one order of 100.
                $this->assertEquals(50.0, $props['changes']['net']);
                $this->assertEquals(200.0, $props['changes']['orders']);
                $this->assertStringContainsString('/ar/admin/reports/sales/export?', $props['export_url']);
                $this->assertStringContainsString('filter%5Bdate_from%5D=2026-11-01', $props['export_url']);
                $page->etc();
            });
    }

    public function test_monthly_interval(): void
    {
        $this->sale(['created_at' => '2026-10-02 10:00'], [['quantity' => 1, 'price' => 30]]);
        $this->sale(['created_at' => '2026-11-04 10:00'], [['quantity' => 1, 'price' => 20]]);

        $props = $this->reportProps('sales', self::PERMISSION, '?filter[preset]=last_month&filter[date_from]=2026-10-01&filter[date_to]=2026-11-30&filter[interval]=month');

        $this->assertSame(['2026-11', '2026-10'], array_column($props['table']['data'], 'bucket'));
        $this->assertSame(['2026-10', '2026-11'], $props['chart']['keys']);
        $this->assertSame('month', $props['filters']['interval']);
    }

    public function test_the_csv_export(): void
    {
        $this->sale(['created_at' => '2026-11-02 10:00', 'channel' => OrderChannel::B2B], [['quantity' => 2, 'price' => 50]]);
        $this->sale(['created_at' => '2026-11-03 10:00'], [['quantity' => 1, 'price' => 30]]);
        $this->sale(['created_at' => '2026-11-04 10:00', 'channel' => OrderChannel::B2B], [['quantity' => 1, 'price' => 0.5]]);

        $rows = $this->csv('sales/export', self::PERMISSION, self::NOVEMBER.'&filter[channel]=b2b');

        $this->assertSame('الفترة', $rows[0][0]);
        $this->assertCount(3, $rows);
        $this->assertSame(['2026-11-02', '1', '2', '100.00', '0.00', '0.00', '0.00', '100.00', '100.00'], $rows[1]);
        $this->assertSame('0.50', $rows[2][7]);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('sales', 'Order View List')->assertForbidden();
        $this->report('sales/export', 'Order View List')->assertForbidden();
    }
}
