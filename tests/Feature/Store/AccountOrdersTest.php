<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Carbon;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Services\ReturnRequestService;

/**
 * The account's orders and their detail, and return requests from it.
 */
class AccountOrdersTest extends StorefrontTestCase
{
    private User $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0599000111', 'password' => 'Secret-pass-123'])
            ->assignRole(UserRole::CUSTOMER->value);
        $this->product = Product::factory()->create(['name' => 'قميص', 'base_price' => 100, 'manage_stock' => true, 'stock_quantity' => 10]);
        $this->actingAs($this->buyer);
    }

    public function test_lists_only_the_users_orders_newest_first(): void
    {
        $old = $this->order(['user_id' => $this->buyer->id, 'created_at' => now()->subDay()]);
        $new = $this->order(['user_id' => $this->buyer->id]);
        $this->order(['user_id' => null, 'contact_phone' => '0599888777']);

        $orders = $this->props('/ar/account/orders', 'Store/Storefront/Account/Orders')['orders'];

        $this->assertSame([$new->order_number, $old->order_number], array_column($orders['data'], 'number'));
        $this->assertSame('100.00 ₪', $orders['data'][0]['total_label']);
        $this->assertSame(1, $orders['data'][0]['items_count']);
    }

    public function test_guest_orders_with_the_accounts_phone_are_never_listed_by_phone(): void
    {
        $guest = $this->order(['user_id' => null, 'contact_phone' => '0599000111']);
        $mine = $this->order(['user_id' => $this->buyer->id, 'contact_phone' => '0599000111']);

        $numbers = array_column($this->props('/ar/account/orders', 'Store/Storefront/Account/Orders')['orders']['data'], 'number');

        $this->assertSame([$mine->order_number], $numbers);
        $this->get($this->siteUrl('/ar/account/orders/'.$guest->order_number))->assertNotFound();
    }

    public function test_a_past_order_is_claimed_with_its_number_and_phone_only(): void
    {
        $guest = $this->order(['user_id' => null, 'contact_phone' => '0599777666']);
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x-secret-123']);
        $theirs = $this->order(['user_id' => $other->id, 'contact_phone' => '0599777666']);
        $generic = 'لم نجد طلبًا بهذا الرقم ورقم الهاتف. تحقق منهما وحاول مرة أخرى.'; // tracking's own message

        $this->claim($guest->order_number, '0599000000')->assertSessionHasErrors(['number' => $generic]);
        $this->claim('NOPE', '0599777666')->assertSessionHasErrors(['number' => $generic]);
        $this->claim($theirs->order_number, '0599777666')->assertSessionHasErrors(['number' => $generic]);
        $this->assertNull($guest->fresh()->user_id);
        $this->assertSame($other->id, $theirs->fresh()->user_id);

        $this->claim($guest->order_number, '+970 599 777 666')->assertSessionHasNoErrors();

        $this->assertSame($this->buyer->id, $guest->fresh()->user_id);
        // Only that one: another guest order with the same phone stays a guest's.
        $sibling = $this->order(['user_id' => null, 'contact_phone' => '0599777666']);
        $this->assertNull($sibling->fresh()->user_id);
        $this->assertSame([$guest->order_number], array_column($this->props('/ar/account/orders', 'Store/Storefront/Account/Orders')['orders']['data'], 'number'));
    }

    public function test_claims_are_limited_per_account(): void
    {
        $guest = $this->order(['user_id' => null, 'contact_phone' => '0599777666']);

        for ($i = 0; $i < 5; $i++) {
            $this->claim('WRONG'.$i, '0599777666');
            \Illuminate\Support\Facades\RateLimiter::clear('store-track:127.0.0.1');
        }

        $this->claim($guest->order_number, '0599777666')->assertSessionHasErrors('number');
        $this->assertNull($guest->fresh()->user_id);
    }

    public function test_detail_shows_lines_totals_timeline_and_coupon_but_no_internal_notes(): void
    {
        $order = $this->order(['user_id' => $this->buyer->id, 'coupon_code' => 'TEN', 'internal_notes' => 'SECRET-NOTE', 'status' => OrderStatus::CONFIRMED]);

        $detail = $this->props('/ar/account/orders/'.$order->order_number, 'Store/Storefront/Account/OrderDetail')['order'];

        $this->assertSame($order->order_number, $detail['number']);
        $this->assertSame('TEN', $detail['coupon_code']);
        $this->assertSame('قميص', $detail['lines'][0]['name']);
        $this->assertSame([['name' => 'المقاس', 'value' => 'L']], $detail['lines'][0]['options']);
        $this->assertSame('100.00 ₪', $detail['totals']['total_label']);
        $this->assertSame(['placed', 'confirmed', 'preparing', 'shipping', 'delivered'], array_column($detail['timeline'], 'key'));
        $this->assertNull($detail['return_form']);
        $this->assertStringNotContainsString('SECRET-NOTE', json_encode($detail, JSON_UNESCAPED_UNICODE));
    }

    public function test_another_users_order_is_404(): void
    {
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x-secret-123']);
        $theirs = $this->order(['user_id' => $other->id]);

        $this->get($this->siteUrl('/ar/account/orders/'.$theirs->order_number))->assertNotFound();
        $this->post($this->siteUrl('/ar/account/orders/'.$theirs->order_number.'/returns'), $this->returnFields($theirs))->assertNotFound();
    }

    public function test_the_order_detail_offers_the_form_when_eligible(): void
    {
        $order = $this->delivered();

        $detail = $this->props('/ar/account/orders/'.$order->order_number, 'Store/Storefront/Account/OrderDetail')['order'];

        $this->assertSame(1, count($detail['return_form']['lines']));
        $this->assertSame(2, $detail['return_form']['lines'][0]['max']);
        $this->assertSame(['return', 'exchange'], array_column($detail['return_form']['types'], 'value'));
        $this->assertNull($detail['return_blocked']);

        Carbon::setTestNow(now()->addDays(30));
        $late = $this->props('/ar/account/orders/'.$order->order_number, 'Store/Storefront/Account/OrderDetail')['order'];
        $this->assertNull($late['return_form']);
        $this->assertNotNull($late['return_blocked']);
        Carbon::setTestNow();
    }

    public function test_customer_submits_a_request(): void
    {
        $order = $this->delivered();

        $this->from($this->siteUrl('/ar/account/orders/'.$order->order_number))
            ->post($this->siteUrl('/ar/account/orders/'.$order->order_number.'/returns'), $this->returnFields($order))
            ->assertSessionHasNoErrors();

        $request = ReturnRequest::query()->sole();
        $this->assertSame([ReturnRequestStatus::REQUESTED, $this->buyer->id, 1], [$request->status, $request->user_id, $request->lines->sole()->quantity]);

        // A second one waits until this one is closed.
        $this->from($this->siteUrl('/ar/account/orders/'.$order->order_number))
            ->post($this->siteUrl('/ar/account/orders/'.$order->order_number.'/returns'), $this->returnFields($order))
            ->assertSessionHasErrors('order');
    }

    public function test_the_buyer_cancels_a_request_that_is_still_waiting(): void
    {
        $order = $this->delivered();
        $url = '/ar/account/orders/'.$order->order_number;
        $this->post($this->siteUrl($url.'/returns'), $this->returnFields($order))->assertSessionHasNoErrors();
        $request = ReturnRequest::query()->sole();
        $this->assertTrue($this->props($url, 'Store/Storefront/Account/OrderDetail')['order']['returns'][0]['can_cancel']);

        $this->from($this->siteUrl($url))->post($this->siteUrl($url.'/returns/'.$request->id.'/cancel'))->assertSessionHasNoErrors();

        $this->assertSame(ReturnRequestStatus::CANCELLED, $request->fresh()->status);
        $detail = $this->props($url, 'Store/Storefront/Account/OrderDetail')['order'];
        $this->assertFalse($detail['returns'][0]['can_cancel']);
        $this->assertSame(2, $detail['return_form']['lines'][0]['max'], 'a cancelled request takes nothing');

        // Once staff approved it, the buyer cannot cancel it.
        $this->post($this->siteUrl($url.'/returns'), $this->returnFields($order))->assertSessionHasNoErrors();
        $second = ReturnRequest::query()->latest('id')->first();
        $staff = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::SUPER_ADMIN->value))->firstOrFail();
        app(ReturnRequestService::class)->approve($second, null, $staff->id);
        $this->from($this->siteUrl($url))->post($this->siteUrl($url.'/returns/'.$second->id.'/cancel'))->assertSessionHasErrors('status');
        $this->assertSame(ReturnRequestStatus::APPROVED, $second->fresh()->status);
    }

    public function test_lines_left_at_zero_are_dropped_and_one_is_needed(): void
    {
        $order = $this->delivered();
        $fields = $this->returnFields($order);
        $fields['lines'][0]['quantity'] = 0;

        $this->from($this->siteUrl('/ar/account/orders/'.$order->order_number))
            ->post($this->siteUrl('/ar/account/orders/'.$order->order_number.'/returns'), $fields)
            ->assertSessionHasErrors('lines');
    }

    public function test_the_customer_sees_status_but_not_staff_notes(): void
    {
        $order = $this->delivered();
        $staff = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::SUPER_ADMIN->value))->firstOrFail();
        $returns = app(ReturnRequestService::class);
        $request = $returns->create($order, ['type' => 'return', 'reason' => 'defective', 'customer_note' => 'ممزق', 'staff_note' => 'STAFF-ONLY', 'lines' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]]], null, $staff->id);
        $returns->reject($request, 'HISTORY-NOTE', $staff->id);

        $detail = $this->props('/ar/account/orders/'.$order->order_number, 'Store/Storefront/Account/OrderDetail')['order'];

        $this->assertSame('rejected', $detail['returns'][0]['status']['key']);
        $this->assertSame(['تم الطلب', 'مرفوض'], array_column($detail['returns'][0]['history'], 'status'));
        $json = json_encode($detail, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('STAFF-ONLY', $json);
        $this->assertStringNotContainsString('HISTORY-NOTE', $json);
        // Rejected: a new request may be made.
        $this->assertNotNull($detail['return_form']);
    }

    private function claim(string $number, string $phone): \Illuminate\Testing\TestResponse
    {
        return $this->from($this->siteUrl('/ar/account/orders'))->post($this->siteUrl('/ar/account/orders/claim'), ['number' => $number, 'phone' => $phone]);
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::query()->create([
            'order_number' => 'N'.random_int(100000, 999999),
            'sub_total' => 100,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 100,
            'status' => OrderStatus::PENDING,
            'contact_phone' => '0599000111',
            ...$attributes,
        ]);

        if (isset($attributes['created_at'])) {
            $order->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => (int) ($attributes['quantity'] ?? 1),
            'price' => 100,
            'total_price' => 100 * (int) ($attributes['quantity'] ?? 1),
            'product_name' => ['ar' => 'قميص', 'en' => 'Shirt'],
            'options' => [['name' => ['ar' => 'المقاس', 'en' => 'Size'], 'value' => ['ar' => 'L', 'en' => 'L']]],
        ]);
        $order->statusHistory()->create(['status' => $order->status]);

        return $order;
    }

    private function delivered(): Order
    {
        $order = $this->order(['user_id' => $this->buyer->id, 'status' => OrderStatus::DELIVERED, 'quantity' => 2]);

        return $order->load(['items', 'statusHistory']);
    }

    private function returnFields(Order $order): array
    {
        return [
            'type' => 'return',
            'reason' => 'wrong_size',
            'customer_note' => 'صغير',
            'lines' => [['order_item_id' => $order->items()->first()->id, 'quantity' => 1, 'exchange_product_id' => null]],
        ];
    }
}
