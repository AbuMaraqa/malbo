<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Enums\MerchantStatus;
use Tests\Feature\Reports\Concerns\MakesReportData;
use Tests\Feature\Wholesale\WholesaleTestCase;

/**
 * Merchant performance (B2B): orders, revenue, average order, last order, balance, tier.
 */
class MerchantReportTest extends WholesaleTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Merchant Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-13';

    private Merchant $gold;

    private Merchant $quiet;

    private Merchant $waiting;

    private Tier $tier;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        Merchant::query()->delete();

        $this->tier = $this->tier('ذهبي', 10);
        $this->rep = $this->reportStaff(['Merchant View List']);
        $this->rep->forceFill(['name' => 'مندوب'])->save();

        $this->gold = $this->merchant(MerchantStatus::APPROVED, ['shop_name' => 'متجر الذهب', 'tier_id' => $this->tier->id, 'sales_rep_id' => $this->rep->id, 'credit_limit' => 1000]);
        $this->quiet = $this->merchant(MerchantStatus::APPROVED, ['shop_name' => 'متجر هادئ']);
        $this->waiting = $this->merchant(MerchantStatus::PENDING, ['shop_name' => 'متجر جديد']);

        $b2b = fn (Merchant $merchant, string $at, float $price, OrderStatus $status = OrderStatus::DELIVERED) => $this->sale(
            ['created_at' => $at, 'channel' => OrderChannel::B2B, 'user_id' => $merchant->user_id, 'status' => $status],
            [['quantity' => 10, 'price' => $price]],
        );

        $b2b($this->gold, '2026-11-03 10:00', 30);
        $b2b($this->gold, '2026-11-08 10:00', 10);
        $b2b($this->gold, '2026-11-09 10:00', 50, OrderStatus::CANCELLED);
        $this->sale(['created_at' => '2026-11-09 10:00', 'user_id' => $this->gold->user_id], [['quantity' => 1, 'price' => 50]]);
        $b2b($this->quiet, '2026-09-10 10:00', 20);

        foreach ([['invoice', 400], ['payment', -150]] as [$type, $amount]) {
            LedgerEntry::query()->create(['merchant_id' => $this->gold->id, 'type' => $type, 'amount' => $amount, 'entry_date' => '2026-11-08']);
        }
    }

    private function rows(string $query = ''): \Illuminate\Support\Collection
    {
        return collect($this->reportProps('merchants', self::PERMISSION, self::RANGE.$query)['table']['data'])->keyBy('shop_name');
    }

    public function test_orders_revenue_aov_last_order_and_tier(): void
    {
        $this->report('merchants', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Merchants/Index');
            $props = $page->toArray()['props'];
            $rows = collect($props['table']['data'])->keyBy('shop_name');

            $this->assertSame('متجر الذهب', $props['table']['data'][0]['shop_name']);
            $this->assertSame(2, $rows['متجر الذهب']['orders']);
            $this->assertSame(20, $rows['متجر الذهب']['units']);
            $this->assertSame('400.00 ₪', $rows['متجر الذهب']['net']);
            $this->assertSame('200.00 ₪', $rows['متجر الذهب']['aov']);
            $this->assertSame('2026-11-08', $rows['متجر الذهب']['last_order']);
            $this->assertSame('ذهبي', $rows['متجر الذهب']['tier']);
            $this->assertSame('مندوب', $rows['متجر الذهب']['sales_rep']);
            $this->assertSame('1,000.00 ₪', $rows['متجر الذهب']['credit_limit']);
            $this->assertSame(0, $rows['متجر هادئ']['orders']);
            $this->assertNull($rows['متجر هادئ']['aov']);
            $this->assertSame('2026-09-10', $rows['متجر هادئ']['last_order']);
            $this->assertNull($rows['متجر جديد']['last_order']);

            $this->assertSame(3, $props['totals']['merchants']);
            $this->assertSame(1, $props['totals']['active']);
            $this->assertSame(2, $props['totals']['orders']);
            $this->assertSame('400.00 ₪', $props['totals']['net']);
            $this->assertSame('250.00 ₪', $props['totals']['balance']);
            $this->assertSame(['متجر الذهب'], $props['chart']['labels']);
            $page->etc();
        });
    }

    public function test_balance_comes_from_the_ledger(): void
    {
        $rows = $this->rows();

        $this->assertSame('250.00 ₪', $rows['متجر الذهب']['balance']);
        $this->assertSame('0.00 ₪', $rows['متجر هادئ']['balance']);
    }

    public function test_only_b2b_sales_of_that_merchant_count(): void
    {
        $this->sale(['created_at' => '2026-11-04 10:00', 'channel' => OrderChannel::B2B, 'user_id' => $this->quiet->user_id, 'status' => OrderStatus::AWAITING_CONFIRMATION], [['quantity' => 1, 'price' => 99]]);

        $rows = $this->rows();

        $this->assertSame('400.00 ₪', $rows['متجر الذهب']['net']);
        $this->assertSame(0, $rows['متجر هادئ']['orders']);
    }

    public function test_filters(): void
    {
        $this->assertSame(['متجر جديد'], $this->rows('&filter[status]=pending')->keys()->all());
        $this->assertSame(['متجر الذهب'], $this->rows('&filter[tier]='.$this->tier->id)->keys()->all());
        $this->assertSame(['متجر الذهب'], $this->rows('&filter[sales_rep]='.$this->rep->id)->keys()->all());
        $this->assertSame(['متجر هادئ'], $this->rows('&filter[search]=هادئ')->keys()->all());
    }

    public function test_descending_sorts_put_empty_values_last(): void
    {
        $this->assertSame('متجر الذهب', $this->rows('&sort=-aov')->keys()->first());
        $this->assertSame(['متجر الذهب', 'متجر هادئ', 'متجر جديد'], $this->rows('&sort=-last_order_at')->keys()->all());
        $this->assertSame(['متجر هادئ', 'متجر الذهب', 'متجر جديد'], $this->rows('&sort=last_order_at')->keys()->all());
    }

    public function test_totals_follow_the_table_filters(): void
    {
        $totals = $this->reportProps('merchants', self::PERMISSION, self::RANGE.'&filter[status]=pending')['totals'];

        $this->assertSame(1, $totals['merchants']);
        $this->assertSame(0, $totals['active']);
        $this->assertSame('0.00 ₪', $totals['net']);

        $this->assertSame('250.00 ₪', $this->reportProps('merchants', self::PERMISSION, self::RANGE.'&filter[tier]='.$this->tier->id)['totals']['balance']);
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('merchants/export', self::PERMISSION, self::RANGE.'&filter[status]=approved');

        $this->assertSame('المتجر', $rows[0][0]);
        $this->assertCount(3, $rows);
        $this->assertSame(['متجر الذهب', 'ذهبي', 'مندوب', '2', '20', '400.00', '200.00', '2026-11-08', '250.00', '1000.00'], [$rows[1][0], $rows[1][2], $rows[1][3], $rows[1][4], $rows[1][5], $rows[1][6], $rows[1][7], $rows[1][8], $rows[1][9], $rows[1][10]]);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('merchants', 'Merchant View List')->assertForbidden();
    }
}
