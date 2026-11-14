<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;

/**
 * Guest tracking (`/track`) is the storefront's: a wholesale order is followed in the portal, and
 * its number and phone get the same answer as an unknown number. Staff still find it by number.
 */
class WholesaleOrderTrackingTest extends WholesaleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('store-track:127.0.0.1');
    }

    public function test_a_b2b_order_is_not_found_by_guest_tracking(): void
    {
        $merchant = $this->merchant();
        $order = $this->order($merchant->user_id, OrderChannel::B2B, 'B2B-TRACK-1');

        $this->from($this->siteUrl('/ar/track'))->post($this->siteUrl('/ar/track'), ['number' => $order->order_number, 'phone' => '0599111222'])
            ->assertSessionHasErrors(['number' => __('We could not find an order with this number and phone. Check them and try again.')]);
    }

    public function test_a_b2c_order_is_still_found(): void
    {
        $order = $this->order(null, OrderChannel::B2C, 'B2C-TRACK-1');

        $this->from($this->siteUrl('/ar/track'))->post($this->siteUrl('/ar/track'), ['number' => $order->order_number, 'phone' => '0599111222'])
            ->assertSessionHasNoErrors();
    }

    public function test_staff_still_find_a_b2b_order_by_number_for_a_return(): void
    {
        $merchant = $this->merchant();
        $order = $this->order($merchant->user_id, OrderChannel::B2B, 'B2B-RETURN-1');
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-track@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');

        $this->actingAs($admin)->get($this->siteUrl('/ar/admin/return-requests/add?order='.$order->order_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('notFound', false)->where('order.id', $order->id)->etc());
    }

    private function order(?int $userId, OrderChannel $channel, string $number): Order
    {
        $order = Order::query()->create([
            'order_number' => $number,
            'user_id' => $userId,
            'channel' => $channel,
            'status' => OrderStatus::AWAITING_CONFIRMATION,
            'contact_phone' => '0599111222',
            'sub_total' => 10,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 10,
        ]);
        $order->statusHistory()->create(['status' => OrderStatus::AWAITING_CONFIRMATION]);

        return $order;
    }
}
