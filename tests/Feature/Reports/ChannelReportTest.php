<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Enums\OrderChannel;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * The store (B2C) and wholesale (B2B) side by side: the same figures, their shares and trend.
 */
class ChannelReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Channel Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-10';

    protected function setUp(): void
    {
        parent::setUp();

        config(['reports.timezone' => 'Asia/Hebron']);
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));

        $this->sale(['created_at' => '2026-11-02 10:00'], [['quantity' => 1, 'price' => 100]]);
        $this->sale(['created_at' => '2026-11-04 10:00', 'delivery_fee' => 10], [['quantity' => 2, 'price' => 25]]);
        $this->sale(['created_at' => '2026-11-04 12:00', 'channel' => OrderChannel::B2B], [['quantity' => 10, 'price' => 15]]);
    }

    public function test_metrics_side_by_side_with_shares(): void
    {
        $this->report('channels', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Channels/Index');
            $channels = $page->toArray()['props']['channels'];

            $this->assertSame(['b2c', 'b2b'], array_column($channels, 'key'));
            [$b2c, $b2b] = $channels;
            $this->assertSame(2, $b2c['summary']['orders']);
            $this->assertSame('150.00 ₪', $b2c['summary']['net']);
            $this->assertSame('10.00 ₪', $b2c['summary']['delivery']);
            $this->assertSame('75.00 ₪', $b2c['summary']['aov']);
            $this->assertEquals(50.0, $b2c['share']['net']);
            $this->assertEquals(66.7, $b2c['share']['orders']);
            $this->assertSame(10, $b2b['summary']['units']);
            $this->assertSame('150.00 ₪', $b2b['summary']['aov']);
            $this->assertEquals(33.3, $b2b['share']['orders']);
            $page->etc();
        });
    }

    public function test_change_against_the_previous_period(): void
    {
        $this->sale(['created_at' => '2026-10-25 10:00', 'channel' => OrderChannel::B2B], [['quantity' => 1, 'price' => 100]]);

        [$b2c, $b2b] = $this->reportProps('channels', self::PERMISSION, self::RANGE)['channels'];

        $this->assertEquals(50.0, $b2b['changes']['net']);
        $this->assertNull($b2c['changes']['net']);
    }

    public function test_the_table_and_chart_per_bucket(): void
    {
        $props = $this->reportProps('channels', self::PERMISSION, self::RANGE);

        $this->assertCount(10, $props['chart']['keys']);
        $this->assertEquals(100, $props['chart']['b2c'][1]);
        $this->assertEquals(50, $props['chart']['b2c'][3]);
        $this->assertEquals(150, $props['chart']['b2b'][3]);

        $rows = collect($props['table']['data'])->keyBy('bucket');
        $this->assertSame(['2026-11-04', '2026-11-02'], $rows->keys()->all());
        $this->assertSame(1, $rows['2026-11-04']['b2c_orders']);
        $this->assertSame('50.00 ₪', $rows['2026-11-04']['b2c_net']);
        $this->assertSame('150.00 ₪', $rows['2026-11-04']['b2b_net']);
        $this->assertEquals(75.0, $rows['2026-11-04']['b2b_share']);
        $this->assertEquals(0.0, $rows['2026-11-02']['b2b_share']);
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('channels/export', self::PERMISSION, self::RANGE);

        $this->assertSame('الفترة', $rows[0][0]);
        $this->assertSame(['2026-11-02', '1', '100.00', '0', '0.00', '100.00', '0.0'], $rows[1]);
        $this->assertSame(['2026-11-04', '1', '50.00', '1', '150.00', '200.00', '75.0'], $rows[2]);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('channels', 'Sales Report View')->assertForbidden();
    }
}
