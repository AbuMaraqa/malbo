<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Services\OrderService;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * The admin side of storefront orders: who ordered and from where, the ones awaiting confirmation,
 * a one-click confirm, and each order's status history.
 */
class OrderConfirmTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrders();
    }

    public function test_the_list_shows_channel_and_customer_name(): void
    {
        $order = $this->guestOrder();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.id', $order->id)
            ->where('table.data.0.channel', 'b2c')
            ->where('table.data.0.channel_label', 'المتجر')
            ->where('table.data.0.customer', 'Guest Buyer')
            ->where('table.data.0.customer_name', 'Guest Buyer')
            ->has('channels'));
    }

    public function test_the_awaiting_confirmation_filter(): void
    {
        $awaiting = $this->guestOrder();
        $this->storeOrder([['product_id' => $this->stocked(5)->id, 'quantity' => 1]])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders?filter[awaiting_confirmation]=1'))->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $awaiting->id));
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders?filter[channel]=b2b'))->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 0));
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders?filter[search]=guest'))->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1));
    }

    /**
     * The page keeps its form after the confirm (Inertia preserves state on a PUT); Edit.vue
     * watches `order.status` to follow it. This pins what the watch reads: the page sent back
     * after the confirm carries the new status and the statuses reachable from it.
     */
    public function test_the_page_after_confirming_carries_the_new_status(): void
    {
        $order = $this->guestOrder();
        $edit = $this->siteUrl('/ar/admin/orders/'.$order->id.'/edit');

        $this->actingAs($this->staff)->from($edit)->put($this->siteUrl('/ar/admin/orders/'.$order->id.'/confirm'))->assertRedirect($edit);

        $this->actingAs($this->staff)->get($edit)->assertInertia(fn (Assert $page) => $page
            ->where('order.status', OrderStatus::CONFIRMED->value)
            ->where('canConfirm', false)
            ->where('statuses', fn ($statuses) => collect($statuses)->pluck('value')->all() === array_map(fn (OrderStatus $status) => $status->value, OrderService::reachableFrom(OrderStatus::CONFIRMED))));
    }

    public function test_confirm_moves_an_awaiting_order_and_writes_history(): void
    {
        $order = $this->guestOrder();

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/orders/'.$order->id.'/confirm'))->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame([OrderStatus::AWAITING_CONFIRMATION, OrderStatus::CONFIRMED], $order->statusHistory()->get()->pluck('status')->all());
    }

    public function test_confirm_refuses_other_statuses_and_needs_update(): void
    {
        $order = $this->guestOrder();
        app(OrderService::class)->changeStatus($order, OrderStatus::CANCELLED, $this->staff->id);

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/orders/'.$order->id.'/confirm'))->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);

        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'v-'.Str::random(6).'@orders.test', 'password' => 'secret-password'])->givePermissionTo('Order View List');
        $this->actingAs($viewer)->put($this->siteUrl('/ar/admin/orders/'.$this->guestOrder()->id.'/confirm'))->assertForbidden();
    }

    public function test_the_edit_page_shows_the_history(): void
    {
        $order = $this->guestOrder();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canConfirm', true)
            ->where('order.coupon_code', null)
            ->has('history', 1)
            ->where('history.0.status', OrderStatus::AWAITING_CONFIRMATION->getLabel())
            ->where('history.0.by', null));
    }

    public function test_a_guest_order_can_be_edited(): void
    {
        $order = $this->guestOrder();
        $line = $order->items()->first();

        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $line->product_id, 'quantity' => 1]], [
            'user_id' => null,
            'branch_id' => null,
            'district_id' => null,
            'status' => OrderStatus::CONFIRMED->value,
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertNull($order->user_id);

        // An order made in the admin still needs its customer and branch.
        $this->storeOrder([['product_id' => $this->stocked(5)->id, 'quantity' => 1]], ['user_id' => null, 'branch_id' => null])
            ->assertSessionHasErrors(['user_id', 'branch_id']);
    }

    public function test_the_form_marks_required_only_what_the_server_requires(): void
    {
        $guest = $this->guestOrder();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$guest->id.'/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('required', ['user_id' => false, 'branch_id' => false, 'district_id' => false]));

        $this->storeOrder([['product_id' => $this->stocked(5)->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$this->lastOrder()->id.'/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('required', ['user_id' => true, 'branch_id' => true, 'district_id' => true]));

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/add'))->assertInertia(fn (Assert $page) => $page
            ->where('required', ['user_id' => true, 'branch_id' => true, 'district_id' => true]));
    }

    private function guestOrder(): Order
    {
        return app(OrderService::class)->checkout([
            'customer_name' => 'Guest Buyer',
            'customer_email' => null,
            'contact_phone' => '0599123456',
            'delivery_address' => 'شارع',
            'city_id' => $this->city->id,
            'district_id' => null,
            'notes' => null,
            'payment_method' => PaymentMethod::CASH->value,
            'delivery_fee' => 20,
            'discount' => 0,
            'coupon_code' => null,
        ], [['product_id' => $this->stocked(5)->id, 'quantity' => 2, 'price' => 100.0]]);
    }
}
