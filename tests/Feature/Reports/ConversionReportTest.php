<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Conversion (carts that became an order ÷ carts started; no visits are tracked) and the average
 * order value of each channel.
 */
class ConversionReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Conversion Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));

        $first = $this->sale(['created_at' => '2026-11-02 10:00'], [['quantity' => 1, 'price' => 100]]);
        $this->sale(['created_at' => '2026-11-03 10:00', 'discount' => 10], [['quantity' => 1, 'price' => 50]]);
        $this->sale(['created_at' => '2026-11-03 11:00', 'status' => OrderStatus::AWAITING_CONFIRMATION], [['quantity' => 1, 'price' => 999]]);
        $wholesale = $this->sale(['created_at' => '2026-11-03 12:00', 'channel' => OrderChannel::B2B], [['quantity' => 10, 'price' => 30]]);

        foreach ([['2026-11-02 09:00', $first->id], ['2026-11-02 12:00', null], ['2026-11-03 09:00', null], ['2026-11-03 10:00', null], ['2026-10-20 10:00', null]] as [$at, $order]) {
            DB::table('store_carts')->insert(['uuid' => (string) Str::uuid(), 'token' => Str::random(48), 'order_id' => $order, 'converted_at' => $order ? $at : null, 'created_at' => $at, 'updated_at' => $at, 'last_activity_at' => $at]);
        }

        foreach ([['2026-11-03 11:00', $wholesale->id], ['2026-11-03 13:00', null]] as [$at, $order]) {
            DB::table('wholesale_carts')->insert(['user_id' => $this->reportStaff([])->id, 'order_id' => $order, 'converted_at' => $order ? $at : null, 'created_at' => $at, 'updated_at' => $at, 'last_activity_at' => $at]);
        }
    }

    public function test_cart_conversion_per_channel(): void
    {
        $this->report('conversion', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Conversion/Index');
            $channels = collect($page->toArray()['props']['channels'])->keyBy('key');

            $this->assertSame(4, $channels['b2c']['carts']);
            $this->assertSame(1, $channels['b2c']['converted']);
            $this->assertEquals(25.0, $channels['b2c']['conversion_rate']);
            $this->assertSame(2, $channels['b2b']['carts']);
            $this->assertEquals(50.0, $channels['b2b']['conversion_rate']);
            $page->etc();
        });
    }

    public function test_aov_per_channel_uses_confirmed_orders(): void
    {
        $props = $this->reportProps('conversion', self::PERMISSION, self::RANGE);
        $channels = collect($props['channels'])->keyBy('key');

        $this->assertSame(2, $channels['b2c']['orders']);
        $this->assertSame('70.00 ₪', $channels['b2c']['aov']);
        $this->assertSame('300.00 ₪', $channels['b2b']['aov']);
        $this->assertSame('146.67 ₪', $props['aov']);
    }

    public function test_the_table_per_bucket(): void
    {
        $props = $this->reportProps('conversion', self::PERMISSION, self::RANGE);
        $rows = collect($props['table']['data'])->keyBy('bucket');

        $this->assertSame(2, $rows['2026-11-02']['b2c_carts']);
        $this->assertEquals(50.0, $rows['2026-11-02']['b2c_rate']);
        $this->assertSame(2, $rows['2026-11-03']['b2b_carts']);
        $this->assertEquals(50.0, $rows['2026-11-03']['b2b_rate']);
        $this->assertEquals(0.0, $rows['2026-11-03']['b2c_rate']);

        $this->assertCount(10, $props['chart']['keys']);
        $this->assertEquals(50, $props['chart']['b2c_rate'][1]);
        $this->assertEquals([100, 40], array_slice($props['chart']['b2c_aov'], 1, 2));
        $this->assertEquals(300, $props['chart']['b2b_aov'][2]);
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('conversion/export', self::PERMISSION, self::RANGE);

        $this->assertSame('الفترة', $rows[0][0]);
        $this->assertSame(['2026-11-02', '2', '1', '50.0', '0', '0', '0.0'], $rows[1]);
        $this->assertCount(3, $rows);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('conversion', 'Sales Report View')->assertForbidden();
    }
}
