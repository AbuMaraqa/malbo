<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartReminder;
use Modules\Store\Services\PricingService;
use Modules\Store\Settings\MarketingSettings;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Abandoned carts: carts started in the period, those left without an order, their value at
 * today's prices, the reminders sent and the carts they brought back.
 */
class CartReportTest extends DatabaseTestCase
{
    use MakesReportData;

    private const PERMISSION = 'Cart Report View';

    private const RANGE = '?filter[date_from]=2026-11-01&filter[date_to]=2026-11-13';

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Cart> */
    private array $carts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        $settings = app(MarketingSettings::class);
        $settings->abandoned_after_minutes = 60;
        $settings->save();

        $this->products = [
            'shirt' => Product::factory()->create(['base_price' => 100, 'sale_price' => 80]),
            'belt' => Product::factory()->create(['base_price' => 50, 'discount_type' => 'percentage', 'discount_value' => 10, 'discount_start_date' => '2026-11-01 00:00:00', 'discount_end_date' => '2026-11-13 00:00:00']),
            'hat' => Product::factory()->create(['base_price' => 40, 'discount_type' => 'fixed', 'discount_value' => 5, 'discount_end_date' => '2026-11-10 00:00:00']),
            'scarf' => Product::factory()->create(['base_price' => 30, 'sale_price' => 0]),
        ];

        $this->carts['reachable'] = $this->cart('2026-11-05 10:00', [['shirt', 2], ['belt', 1]], consent: true);
        $this->remind($this->carts['reachable'], 'mail', '2026-11-06 10:00');
        $this->carts['silent'] = $this->cart('2026-11-06 10:00', [['hat', 1]]);
        $this->remind($this->carts['silent'], 'mail', '2026-11-07 10:00', CartReminder::FAILED);
        $this->carts['recovered'] = $this->cart('2026-11-07 10:00', [['scarf', 1]], order: OrderStatus::DELIVERED);
        $this->remind($this->carts['recovered'], 'sms', '2026-11-08 10:00');
        $this->carts['cancelled'] = $this->cart('2026-11-08 10:00', [['scarf', 1]], order: OrderStatus::CANCELLED);
        $this->remind($this->carts['cancelled'], 'mail', '2026-11-09 10:00');
        $this->carts['ordered'] = $this->cart('2026-11-08 12:00', [['scarf', 2]], order: OrderStatus::CONFIRMED);
        $this->carts['empty'] = $this->cart('2026-11-09 10:00', []);
        $this->carts['fresh'] = $this->cart('2026-11-13 11:30', [['shirt', 1]]);
        $this->carts['old'] = $this->cart('2026-10-20 10:00', [['shirt', 1]]);
    }

    /**
     * @param  list<array{0: string, 1: int}>  $items
     */
    private function cart(string $at, array $items, bool $consent = false, ?OrderStatus $order = null): Cart
    {
        $cart = new Cart;
        $cart->forceFill([
            'uuid' => (string) Str::uuid(),
            'token' => Str::random(48),
            'created_at' => $at,
            'updated_at' => $at,
            'last_activity_at' => $at,
            'contact_consent_at' => $consent ? $at : null,
            'contact_email' => $consent ? 'buyer@example.com' : null,
        ])->save();

        foreach ($items as [$key, $quantity]) {
            $cart->items()->create(['product_id' => $this->products[$key]->id, 'quantity' => $quantity]);
        }

        if ($order !== null) {
            /** @var Order $placed */
            $placed = $this->sale(['created_at' => $at, 'status' => $order], [['quantity' => 1, 'price' => 30]]);
            $cart->forceFill(['order_id' => $placed->id, 'converted_at' => $at])->save();
        }

        return $cart;
    }

    private function remind(Cart $cart, string $channel, string $at, string $status = CartReminder::SENT): void
    {
        CartReminder::query()->create(['cart_id' => $cart->id, 'sequence' => 1, 'channel' => $channel, 'recipient' => 'x', 'status' => $status, 'sent_at' => $status === CartReminder::SENT ? $at : null]);
    }

    public function test_started_converted_abandoned_and_reachable(): void
    {
        $this->report('carts', self::PERMISSION, self::RANGE)->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Reports/Carts/Index');
            $summary = $page->toArray()['props']['summary'];

            $this->assertSame(7, $summary['started']);
            $this->assertSame(3, $summary['converted']);
            $this->assertSame(2, $summary['abandoned']);
            $this->assertSame(1, $summary['reachable']);
            $this->assertSame('245.00 ₪', $summary['value']);
            $page->etc();
        });
    }

    public function test_value_at_todays_prices_matches_the_pricing_service(): void
    {
        $pricing = app(PricingService::class);
        $expected = 2 * $pricing->for($this->products['shirt']->fresh())->amount + $pricing->for($this->products['belt']->fresh())->amount + $pricing->for($this->products['hat']->fresh())->amount;

        $this->assertEquals(80.0, $pricing->for($this->products['shirt']->fresh())->amount);
        $this->assertEquals(45.0, $pricing->for($this->products['belt']->fresh())->amount);
        $this->assertEquals(40.0, $pricing->for($this->products['hat']->fresh())->amount);
        $this->assertSame(number_format($expected, 2).' ₪', $this->reportProps('carts', self::PERMISSION, self::RANGE)['summary']['value']);

        // The day after the belt's window, its 10% is gone in both.
        $this->travelTo(Carbon::parse('2026-11-14 08:00:00'));
        $this->assertEquals(50.0, $pricing->for($this->products['belt']->fresh())->amount);
        // The fresh cart has now been quiet an hour too: 2 × 80 + 50 + 40 + 80.
        $this->assertSame('330.00 ₪', $this->reportProps('carts', self::PERMISSION, self::RANGE)['summary']['value']);
    }

    public function test_recovered_needs_a_sent_reminder_and_a_live_order(): void
    {
        $summary = $this->reportProps('carts', self::PERMISSION, self::RANGE)['summary'];

        $this->assertSame(3, $summary['reminded']);
        $this->assertSame(1, $summary['recovered']);
        $this->assertSame('30.00 ₪', $summary['recovered_value']);
        $this->assertEquals(33.3, $summary['recovery_rate']);
    }

    public function test_reminders_by_channel_in_the_period(): void
    {
        $props = $this->reportProps('carts', self::PERMISSION, self::RANGE);

        $this->assertSame(['mail' => 2, 'sms' => 1], collect($props['reminders'])->pluck('sent', 'channel')->all());
        $this->assertSame([], $this->reportProps('carts', self::PERMISSION, '?filter[date_from]=2026-11-10&filter[date_to]=2026-11-13')['reminders']);
    }

    public function test_the_table_per_bucket(): void
    {
        $rows = collect($this->reportProps('carts', self::PERMISSION, self::RANGE)['table']['data'])->keyBy('bucket');

        $this->assertSame(1, $rows['2026-11-05']['abandoned']);
        $this->assertSame('205.00 ₪', $rows['2026-11-05']['value']);
        $this->assertSame(2, $rows['2026-11-08']['started']);
        $this->assertSame(2, $rows['2026-11-08']['converted']);
        $this->assertSame(0, $rows['2026-11-08']['recovered']);
    }

    public function test_the_csv_export(): void
    {
        $rows = $this->csv('carts/export', self::PERMISSION, self::RANGE);

        $this->assertSame('الفترة', $rows[0][0]);
        $this->assertSame(['2026-11-05', '1', '0', '1', '205.00', '1', '0', '0.00'], $rows[1]);
        $this->assertCount(7, $rows);
    }

    public function test_staff_need_the_permission(): void
    {
        $this->report('carts', 'Abandoned Cart View List')->assertForbidden();
    }
}
