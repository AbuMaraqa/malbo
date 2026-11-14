<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ShipmentService;
use Modules\Store\Services\OrderTracking;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * Shipments in the admin: the list, and the card on the order page that makes a shipment and
 * moves it along. The buyer's tracking page names the courier and the tracking number.
 */
class ShipmentPagesTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();
    }

    public function test_the_list_is_shaped_for_the_table(): void
    {
        [$order, $shipment] = $this->shipped('TRK-1');

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/shipments'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Shipment/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $shipment->id)
            ->where('table.data.0.order_id', $order->id)
            ->where('table.data.0.order_number', $order->order_number)
            ->where('table.data.0.customer', 'Ahmad Customer')
            ->where('table.data.0.courier', 'توصيل المتجر')
            ->where('table.data.0.tracking_number', 'TRK-1')
            ->where('table.data.0.status', 'pending')
            ->where('table.data.0.cost', '12.50')
            ->has('statuses', 6)
            ->has('couriers', 1));
    }

    public function test_each_filter(): void
    {
        [$order, $pending] = $this->shipped('TRK-1');
        [, $moving] = $this->shipped('TRK-2');
        app(ShipmentService::class)->move($moving, ShipmentStatus::PICKED_UP, null, $this->staff->id);

        $only = fn (string $query, Shipment $expected) => $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/shipments?'.$query))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $expected->id));

        $only('filter[status]=picked_up', $moving);
        $only('filter[order_number]='.$order->order_number, $pending);
        $only('filter[search]=trk-2', $moving);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/shipments?filter[courier]=manual'))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/shipments?filter[created_at_from]=2999-01-01'))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
    }

    public function test_the_order_page_carries_the_shipment(): void
    {
        // Confirmed is not packed yet: a shipment starts once the order is being prepared.
        $confirmed = $this->orderAt([OrderStatus::CONFIRMED]);
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$confirmed->id.'/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('shipment.current', null)
            ->where('shipment.can_create', false));

        $order = $this->orderAt([OrderStatus::CONFIRMED, OrderStatus::PREPARING]);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shipment.current', null)
            ->where('shipment.can_create', true)
            ->where('shipment.couriers', [['value' => 'manual', 'label' => 'توصيل المتجر']]));

        $shipment = app(ShipmentService::class)->create($order, ['courier' => 'manual', 'tracking_number' => 'TRK-9'], $this->staff->id);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('shipment.current.id', $shipment->id)
            ->where('shipment.current.tracking_number', 'TRK-9')
            ->where('shipment.current.status.value', 'pending')
            ->where('shipment.current.transitions', fn ($transitions) => collect($transitions)->pluck('value')->all() === ['picked_up', 'in_transit', 'delivered', 'cancelled'])
            ->has('shipment.current.events', 1)
            ->where('shipment.can_create', false));
    }

    public function test_create_and_move_from_the_order_page(): void
    {
        $order = $this->orderAt([OrderStatus::CONFIRMED, OrderStatus::PREPARING]);
        $from = $this->siteUrl('/ar/admin/orders/'.$order->id.'/edit');

        $this->actingAs($this->staff)->from($from)->post($this->siteUrl('/ar/admin/shipments'), [
            'order_id' => $order->id,
            'courier' => 'manual',
            'tracking_number' => 'TRK-5',
            'cost' => '10.5',
            'notes' => 'Call first',
        ])->assertRedirect($from)->assertSessionHasNoErrors();

        $shipment = Shipment::query()->sole();
        $this->assertSame(['TRK-5', '10.50', $this->staff->id], [$shipment->tracking_number, $shipment->cost, $shipment->created_by]);

        $this->actingAs($this->staff)->from($from)->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'picked_up', 'note' => 'Driver Sami'])
            ->assertRedirect($from)->assertSessionHasNoErrors();

        $this->assertSame(ShipmentStatus::PICKED_UP, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);

        $this->actingAs($this->staff)->from($from)->post($this->siteUrl('/ar/admin/shipments'), ['order_id' => $order->id, 'courier' => 'nope'])
            ->assertSessionHasErrors('courier');
    }

    public function test_a_refused_move_shows_the_message(): void
    {
        $order = $this->orderAt([OrderStatus::CONFIRMED, OrderStatus::PREPARING]);
        $shipment = app(ShipmentService::class)->create($order, ['courier' => 'manual'], $this->staff->id);

        $this->actingAs($this->staff)->from($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))
            ->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'delivered'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ShipmentStatus::PENDING, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
    }

    public function test_tracking_shows_the_courier_and_tracking_number(): void
    {
        [$order] = $this->shipped('TRK-77');

        $result = app(OrderTracking::class)->result($order->fresh(['items', 'statusHistory', 'shipment']));
        $this->assertSame(['courier' => 'توصيل المتجر', 'tracking_number' => 'TRK-77'], $result['shipment']);

        $this->get($this->siteUrl('/ar/track'))->assertOk();
        $this->post($this->siteUrl('/ar/track'), ['number' => $order->order_number, 'phone' => '0599000000'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('result.shipment.tracking_number', 'TRK-77'));

        $plain = $this->orderAt([]);
        $this->assertNull(app(OrderTracking::class)->result($plain->fresh(['items', 'statusHistory', 'shipment']))['shipment']);
    }

    public function test_every_action_needs_its_permission(): void
    {
        [$order, $shipment] = $this->shipped('TRK-1');
        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Order Update');

        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/shipments'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl('/ar/admin/shipments'), ['order_id' => $order->id, 'courier' => 'manual'])->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'cancelled'])->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shipment', null));

        $this->assertSame(ShipmentStatus::PENDING, $shipment->fresh()->status);
    }

    public function test_super_admin_has_the_permissions_and_the_sidebar_item(): void
    {
        $role = Role::findByName('Super Admin');

        foreach (['View List', 'Create', 'Update'] as $action) {
            $this->assertTrue($role->hasPermissionTo('Shipment '.$action), $action);
        }

        $this->actingAs($this->staff);
        $this->assertContains('shipments.index', collect(app(SidebarService::class)->all())->pluck('route')->all());
    }

    /**
     * @return array{0: Order, 1: Shipment}
     */
    private function shipped(string $tracking): array
    {
        $order = $this->orderAt([OrderStatus::CONFIRMED, OrderStatus::PREPARING]);

        return [$order, app(ShipmentService::class)->create($order, ['courier' => 'manual', 'tracking_number' => $tracking, 'cost' => 12.5], $this->staff->id)];
    }

    /**
     * @param  list<OrderStatus>  $path
     */
    private function orderAt(array $path): Order
    {
        $this->storeOrder([['product_id' => $this->stocked(10)->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        foreach ($path as $status) {
            app(OrderService::class)->changeStatus($order, $status, $this->staff->id);
        }

        return $order->fresh();
    }
}
