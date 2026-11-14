<?php

namespace Tests\Feature\Items;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Services\OrderService;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * An order placed with a coupon keeps the coupon when staff edit its lines: the discount is worked
 * out again on the new lines with the coupon's rules, and drops to 0 when it no longer applies.
 */
class OrderCouponEditTest extends DatabaseTestCase
{
    use MakesOrders;

    private Product $product;

    private Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrders();
        $this->product = Product::factory()->create(['base_price' => 100, 'sale_price' => null, 'manage_stock' => false]);
        $this->offer = Offer::query()->create([
            'code' => 'TWENTY',
            'type' => OfferType::PERCENTAGE,
            'value' => 20,
            'is_active' => true,
            'min_purchase_amount' => 0,
            'usage_limit' => 10,
            'usage_limit_per_user' => 1,
            'offerable_type' => $this->product->getMorphClass(),
            'offerable_id' => $this->product->id,
            'created_by' => $this->staff->id,
        ]);
    }

    public function test_editing_lines_recomputes_the_coupon_discount(): void
    {
        $order = $this->couponOrder(2);
        $this->assertSame(['200.00', '40.00', '180.00'], [$order->sub_total, $order->discount, $order->total]);

        $this->edit($order, 1)->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(['100.00', '20.00', '20.00', '100.00'], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total]);
        $this->assertSame('TWENTY', $order->coupon_code);
    }

    public function test_a_coupon_that_no_longer_applies_drops_to_zero_with_a_warning(): void
    {
        $order = $this->couponOrder(2);
        $this->offer->update(['min_purchase_amount' => 150]);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('couponNoLongerApplies', false));

        $this->edit($order, 1)->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(['100.00', '0.00', '120.00'], [$order->sub_total, $order->discount, $order->total]);

        $this->offer->delete();
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('couponNoLongerApplies', true));
    }

    private function couponOrder(int $quantity): Order
    {
        return app(OrderService::class)->checkout([
            'customer_name' => 'Guest',
            'customer_email' => null,
            'contact_phone' => '0599123456',
            'delivery_address' => 'شارع',
            'city_id' => $this->city->id,
            'district_id' => null,
            'notes' => null,
            'payment_method' => PaymentMethod::CASH->value,
            'delivery_fee' => 20,
            'discount' => 40,
            'coupon_code' => 'TWENTY',
        ], [['product_id' => $this->product->id, 'quantity' => $quantity, 'price' => 100.0]])->refresh();
    }

    private function edit(Order $order, int $quantity)
    {
        $line = $order->items()->first();

        return $this->updateOrder($order, [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => $quantity]], [
            'user_id' => null,
            'branch_id' => null,
            'district_id' => null,
            'delivery_fee' => 20,
        ]);
    }
}
