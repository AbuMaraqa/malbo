<?php

namespace Tests\Feature\Store;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Services\OrderService;

/**
 * Order tracking without an account: the order number and the phone it was placed with. A wrong
 * pair reveals nothing, and the result holds only the status, its dates, the lines and the totals.
 */
class TrackOrderTest extends StorefrontTestCase
{
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::factory()->create(['name' => 'قميص', 'manage_stock' => false]);
        $this->order = app(OrderService::class)->checkout([
            'customer_name' => 'Secret Name',
            'customer_email' => 'secret@example.com',
            'contact_phone' => '0599 123 456',
            'delivery_address' => 'Secret Street 9',
            'city_id' => null,
            'district_id' => null,
            'notes' => 'ring twice',
            'payment_method' => PaymentMethod::CASH->value,
            'delivery_fee' => 20,
            'discount' => 0,
            'coupon_code' => null,
        ], [['product_id' => $product->id, 'quantity' => 2, 'price' => 100.0]]);
        $this->order->update(['internal_notes' => 'staff only']);
    }

    public function test_the_page_prefills_the_number(): void
    {
        $props = $this->props('/ar/track?number='.$this->order->order_number, 'Store/Storefront/TrackOrder');

        $this->assertSame($this->order->order_number, $props['number']);
        $this->assertNull($props['result']);
    }

    public function test_a_matching_number_and_phone_show_the_order(): void
    {
        $result = $this->trackingResult($this->find($this->order->order_number, '0599123456'));

        $this->assertSame($this->order->order_number, $result['number']);
        $this->assertSame('awaiting_confirmation', $result['status']['key']);
        $this->assertSame(['placed', 'confirmed', 'preparing', 'shipping', 'delivered'], array_column($result['timeline'], 'key'));
        $this->assertSame([true, false, false, false, false], array_column($result['timeline'], 'reached'));
        $this->assertNotNull($result['timeline'][0]['date']);
        $this->assertSame('قميص', $result['lines'][0]['name']);
        $this->assertSame(2, $result['lines'][0]['quantity']);
        $this->assertSame('220.00 ₪', $result['totals']['total_label']);
    }

    public function test_the_phone_is_matched_loosely(): void
    {
        $this->find($this->order->order_number, '+970-599-123-456')->assertOk()->assertInertia(fn (Assert $page) => $page->where('result.number', $this->order->order_number));
        $this->find(' '.$this->order->order_number.' ', '599123456')->assertOk();
    }

    public function test_a_wrong_phone_and_an_unknown_number_say_the_same(): void
    {
        $this->find($this->order->order_number, '0599000000')->assertSessionHasErrors('number');
        $wrongPhone = session('errors')->getBag('default')->toArray();

        $this->find('999999999', '0599123456')->assertSessionHasErrors('number');
        $unknown = session('errors')->getBag('default')->toArray();

        // Word for word the same, and nothing else said: a guesser learns nothing.
        $this->assertSame($wrongPhone, $unknown);
        $this->assertSame(['number' => ['لم نجد طلبًا بهذا الرقم ورقم الهاتف. تحقق منهما وحاول مرة أخرى.']], $wrongPhone);
    }

    public function test_the_result_holds_nothing_private(): void
    {
        $json = json_encode($this->trackingResult($this->find($this->order->order_number, '0599123456')), JSON_UNESCAPED_UNICODE);

        foreach (['Secret Name', 'secret@example.com', 'Secret Street', 'ring twice', 'staff only', '0599 123 456'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_a_cancelled_order_ends_its_timeline(): void
    {
        $staff = User::query()->firstOrFail();
        app(OrderService::class)->changeStatus($this->order, OrderStatus::CONFIRMED, $staff->id);
        app(OrderService::class)->changeStatus($this->order->fresh(), OrderStatus::CANCELLED, $staff->id);

        $result = $this->trackingResult($this->find($this->order->order_number, '0599123456'));

        $this->assertSame(['placed', 'confirmed', 'cancelled'], array_column($result['timeline'], 'key'));
        $this->assertSame([true, true, true], array_column($result['timeline'], 'reached'));
        $this->assertTrue($result['final']);
        $this->assertSame(['done', 'done', 'danger'], array_column($result['timeline'], 'tone'));
    }

    public function test_an_exchanged_order_is_not_shown_as_a_failure(): void
    {
        $staff = User::query()->firstOrFail();
        foreach ([OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED, OrderStatus::EXCHANGED] as $status) {
            app(OrderService::class)->changeStatus($this->order->fresh(), $status, $staff->id);
        }

        $result = $this->trackingResult($this->find($this->order->order_number, '0599123456'));

        $this->assertSame(['placed', 'confirmed', 'preparing', 'shipping', 'delivered', 'exchanged'], array_column($result['timeline'], 'key'));
        $this->assertSame('info', $result['timeline'][5]['tone']);
    }

    public function test_lookups_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->find('1', '0599000000');
        }

        $this->find($this->order->order_number, '0599123456')->assertSessionHasErrors('number');
        $this->assertStringContainsString('محاولات', session('errors')->first('number'));
    }

    private function find(string $number, string $phone): TestResponse
    {
        return $this->from($this->siteUrl('/ar/track'))->post($this->siteUrl('/ar/track'), ['number' => $number, 'phone' => $phone]);
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingResult(TestResponse $response): array
    {
        $result = [];
        $response->assertOk()->assertInertia(function (Assert $page) use (&$result) {
            $page->component('Store/Storefront/TrackOrder');
            $result = $page->toArray()['props']['result'];
        });

        return $result;
    }
}
