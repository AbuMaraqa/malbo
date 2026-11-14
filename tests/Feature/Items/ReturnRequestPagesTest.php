<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Settings\StoreReturnSettings;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * The admin's return and exchange requests: a list with filters, a request made by staff, and the
 * detail page that moves a request along.
 */
class ReturnRequestPagesTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();
    }

    public function test_the_list_is_shaped_for_the_table(): void
    {
        [$order, $request] = $this->request();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/ReturnRequest/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $request->id)
            ->where('table.data.0.order_number', $order->order_number)
            ->where('table.data.0.customer', 'Ahmad Customer')
            ->where('table.data.0.type', 'return')
            ->where('table.data.0.status', 'requested')
            ->where('table.data.0.units', 1)
            ->where('table.data.0.refund_amount', '100.00')
            ->where('windowDays', 14)
            ->has('statuses', 6)
            ->has('types', 2));
    }

    public function test_each_filter(): void
    {
        [$order, $request] = $this->request();
        [, $exchange] = $this->request('exchange');
        app(ReturnRequestService::class)->reject($exchange, null, $this->staff->id);

        $only = fn (string $query, ReturnRequest $expected) => $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests?'.$query))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $expected->id));

        $only('filter[status]=requested', $request);
        $only('filter[type]=exchange', $exchange);
        $only('filter[order_number]='.$order->order_number, $request);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests?filter[created_at_from]=2999-01-01'))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
    }

    public function test_the_open_filter(): void
    {
        [, $open] = $this->request();
        [, $closed] = $this->request();
        app(ReturnRequestService::class)->reject($closed, null, $this->staff->id);

        $ids = fn (string $value) => $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests?filter[open]='.$value))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $value === '1' ? $open->id : $closed->id));

        $ids('1');
        $ids('0');
    }

    public function test_add_finds_the_order_and_creates(): void
    {
        $order = $this->delivered(2);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests/add?order='.$order->order_number))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/ReturnRequest/Add')
                ->where('order.id', $order->id)
                ->where('order.lines.0.max', 2)
                ->where('blocked', null));

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests/add?order=NOPE'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order', null)->where('notFound', true));

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/return-requests'), [
            'order_id' => $order->id,
            'type' => 'return',
            'reason' => 'defective',
            'staff_note' => 'من الهاتف',
            'lines' => [['order_item_id' => $order->items->first()->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $request = ReturnRequest::query()->sole();
        $this->assertSame([$this->staff->id, 'من الهاتف', $this->customer->id], [$request->created_by, $request->staff_note, $request->user_id]);
    }

    public function test_edit_shows_the_request(): void
    {
        [$order, $request] = $this->request();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/ReturnRequest/Edit')
                ->where('request.id', $request->id)
                ->where('request.order.number', $order->order_number)
                ->where('request.status.value', 'requested')
                ->where('request.lines.0.quantity', 1)
                ->where('transitions', [['value' => 'approved', 'label' => 'موافق عليه'], ['value' => 'rejected', 'label' => 'مرفوض']])
                ->has('request.history', 1));
    }

    public function test_edit_shows_the_refund_breakdown(): void
    {
        $product = $this->stocked(10, ['base_price' => 200]);
        $order = $this->deliveredWith($product, 1);
        $order->forceFill(['discount' => 50, 'total' => 150])->save();
        $request = app(ReturnRequestService::class)->create($order->fresh(), $this->fields($order), $this->customer);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('request.lines.0.unit_price', '200.00')
                ->where('request.lines.0.unit_discount', '50.00')
                ->where('request.lines.0.refund', '150.00')
                ->where('request.order.discount', '50.00')
                ->where('request.refund_amount', '150.00'));
    }

    public function test_status_actions(): void
    {
        $product = $this->stocked(10);
        $order = $this->deliveredWith($product, 1);
        $request = app(ReturnRequestService::class)->create($order, $this->fields($order), null, $this->staff->id);

        foreach (['approved', 'received', 'completed'] as $to) {
            $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/status'), ['status' => $to, 'note' => 'ok'])->assertSessionHasNoErrors();
        }

        $this->assertSame(ReturnRequestStatus::COMPLETED, $request->fresh()->status);
        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/status'), ['status' => 'approved'])->assertSessionHasErrors('status');
    }

    public function test_complete_shortage_shows_the_message(): void
    {
        $product = $this->stocked(10);
        $other = $this->stocked(1);
        $order = $this->deliveredWith($product, 1);
        $request = app(ReturnRequestService::class)->create($order, $this->fields($order, 'exchange', $other->id), null, $this->staff->id);
        app(ReturnRequestService::class)->approve($request, null, $this->staff->id);
        app(ReturnRequestService::class)->receive($request->fresh(), null, $this->staff->id);
        $other->update(['stock_quantity' => 0]);

        $this->actingAs($this->staff)->from($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/edit'))
            ->put($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/status'), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ReturnRequestStatus::RECEIVED, $request->fresh()->status);
    }

    public function test_refunded_flag_and_note(): void
    {
        [, $request] = $this->request();

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/return-requests/'.$request->id), ['staff_note' => 'اتصلنا', 'refunded' => true])
            ->assertSessionHasErrors('refunded');

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/return-requests/'.$request->id), ['staff_note' => 'اتصلنا', 'refunded' => false])
            ->assertSessionHasNoErrors();
        $this->assertSame('اتصلنا', $request->fresh()->staff_note);
    }

    public function test_the_window_setting_is_saved(): void
    {
        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/return-requests/settings'), ['return_window_days' => 30])->assertSessionHasNoErrors();
        $this->assertSame(30, app(StoreReturnSettings::class)->return_window_days);

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/return-requests/settings'), ['return_window_days' => -1])->assertSessionHasErrors('return_window_days');
    }

    public function test_exchange_products_are_looked_up(): void
    {
        $this->stocked(5, ['name' => 'Linen shirt']);

        $this->actingAs($this->staff)->getJson($this->siteUrl('/ar/admin/return-requests/products?q=linen'))->assertOk()->assertJsonPath('0.label', 'Linen shirt');
    }

    public function test_every_action_needs_its_permission(): void
    {
        [$order, $request] = $this->request();
        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Return Request View List');

        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/return-requests'))->assertOk();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/return-requests/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl('/ar/admin/return-requests'), ['order_id' => $order->id])->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/return-requests/products'))->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/edit'))->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl('/ar/admin/return-requests/'.$request->id), ['staff_note' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/status'), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl('/ar/admin/return-requests/settings'), ['return_window_days' => 3])->assertForbidden();

        $this->assertSame(ReturnRequestStatus::REQUESTED, $request->fresh()->status);
    }

    public function test_super_admin_has_the_permissions_and_the_sidebar_item(): void
    {
        $role = Role::findByName('Super Admin');

        foreach (['View List', 'Create', 'Update'] as $action) {
            $this->assertTrue($role->hasPermissionTo('Return Request '.$action), $action);
        }

        $this->actingAs($this->staff);
        $this->assertContains('return-requests.index', collect(app(SidebarService::class)->all())->pluck('route')->all());
    }

    /**
     * @return array{0: Order, 1: ReturnRequest}
     */
    private function request(string $type = 'return'): array
    {
        $product = $this->stocked(10);
        $order = $this->deliveredWith($product, 1);
        $exchange = $type === 'exchange' ? $this->stocked(5)->id : null;

        // A buyer returns; staff exchange for another product (a buyer may only pick a sibling variant).
        $request = $exchange === null
            ? app(ReturnRequestService::class)->create($order, $this->fields($order), $this->customer)
            : app(ReturnRequestService::class)->create($order, $this->fields($order, $type, $exchange), null, $this->staff->id);

        return [$order, $request];
    }

    private function delivered(int $quantity): Order
    {
        return $this->deliveredWith($this->stocked(10), $quantity);
    }

    private function deliveredWith(Product $product, int $quantity): Order
    {
        $this->storeOrder([['product_id' => $product->id, 'quantity' => $quantity]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        foreach ([OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED] as $status) {
            app(OrderService::class)->changeStatus($order, $status, $this->staff->id);
        }

        return $order->fresh(['items']);
    }

    private function fields(Order $order, string $type = 'return', ?int $exchange = null): array
    {
        return [
            'type' => $type,
            'reason' => 'wrong_size',
            'lines' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1, 'exchange_product_id' => $exchange]],
        ];
    }
}
