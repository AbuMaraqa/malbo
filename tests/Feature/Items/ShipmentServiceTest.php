<?php

namespace Tests\Feature\Items;

use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Shipment;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Services\Couriers\CourierGateway;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Items\Services\ShipmentService;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * A shipment carries an order to the buyer: a courier, a tracking number and a status. Moving it
 * moves the order through OrderService, and only where the order may go.
 */
class ShipmentServiceTest extends DatabaseTestCase
{
    use MakesOrders;

    private ShipmentService $shipments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();

        $this->shipments = app(ShipmentService::class);
    }

    public function test_a_shipment_is_created_with_a_pending_event(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);

        $shipment = $this->shipments->create($order, ['courier' => 'manual', 'tracking_number' => 'TRK-1', 'cost' => 15, 'notes' => 'Fragile'], $this->staff->id);

        $this->assertSame([ShipmentStatus::PENDING, 'manual', 'TRK-1', '15.00', 'Fragile', $this->staff->id], [
            $shipment->status, $shipment->courier, $shipment->tracking_number, $shipment->cost, $shipment->notes, $shipment->created_by,
        ]);
        $this->assertSame([ShipmentStatus::PENDING], $shipment->events()->pluck('status')->all());
        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
    }

    public function test_only_one_active_shipment_per_order(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);
        $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $this->assertRefused('order_id', fn () => $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id));
        $this->assertSame(1, Shipment::query()->where('order_id', $order->id)->count());
    }

    public function test_a_shipment_needs_an_order_on_its_way(): void
    {
        // Shipments start once the order is being prepared: a confirmed order is not packed yet.
        foreach ([OrderStatus::PENDING, OrderStatus::CONFIRMED, OrderStatus::DELIVERED, OrderStatus::CANCELLED] as $status) {
            $order = $this->orderAt($status);

            $this->assertRefused('order_id', fn () => $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id));
        }

        $pickup = $this->orderAt(OrderStatus::PREPARING, overrides: ['delivery_type' => 2]);
        $this->assertRefused('order_id', fn () => $this->shipments->create($pickup, ['courier' => 'manual'], $this->staff->id));

        $this->assertSame(0, Shipment::query()->count());
    }

    public function test_an_unknown_courier_is_refused(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);

        $this->assertRefused('courier', fn () => $this->shipments->create($order, ['courier' => 'dhl'], $this->staff->id));
    }

    public function test_picked_up_moves_the_order_to_with_courier(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $moved = $this->shipments->move($shipment, ShipmentStatus::PICKED_UP, 'At the door', $this->staff->id);

        $this->assertSame(ShipmentStatus::PICKED_UP, $moved->status);
        $this->assertNotNull($moved->shipped_at);
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY, $order->statusHistory()->get()->last()->status);
        $this->assertSame('At the door', $moved->events()->get()->last()->note);
    }

    public function test_in_transit_after_picked_up_leaves_the_order_alone(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $this->shipments->move($shipment, ShipmentStatus::PICKED_UP, null, $this->staff->id);
        $this->shipments->move($shipment, ShipmentStatus::IN_TRANSIT, null, $this->staff->id);

        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);
        $this->assertSame(1, $order->statusHistory()->where('status', OrderStatus::OUT_FOR_DELIVERY->value)->count());
        $this->assertSame(3, $shipment->events()->count());
    }

    public function test_delivered_delivers_the_order(): void
    {
        $order = $this->orderAt(OrderStatus::OUT_FOR_DELIVERY);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $moved = $this->shipments->move($shipment, ShipmentStatus::DELIVERED, null, $this->staff->id);

        $this->assertNotNull($moved->delivered_at);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    public function test_returned_to_sender_returns_the_order_and_restocks_once(): void
    {
        $product = $this->stocked(10);
        $order = $this->orderAt(OrderStatus::PREPARING, $product, 2);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $this->shipments->move($shipment, ShipmentStatus::PICKED_UP, null, $this->staff->id);
        $this->shipments->move($shipment, ShipmentStatus::RETURNED_TO_SENDER, 'Nobody home', $this->staff->id);

        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(2, (int) StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::RETURN->value)->sum('quantity'));
    }

    public function test_returned_to_sender_after_a_partial_return_restocks_only_the_rest(): void
    {
        $product = $this->stocked(10);
        $order = $this->orderAt(OrderStatus::PREPARING, $product, 3);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);
        $this->shipments->move($shipment, ShipmentStatus::PICKED_UP, null, $this->staff->id);
        // Delivered by hand (the shipment stays picked up), then one unit comes back through a return request.
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::DELIVERED, $this->staff->id);
        $returns = app(ReturnRequestService::class);
        $request = $returns->create($order->fresh(['items']), [
            'type' => 'return',
            'reason' => 'wrong_size',
            'lines' => [['order_item_id' => $order->items()->first()->id, 'quantity' => 1]],
        ], null, $this->staff->id);
        $returns->approve($request, null, $this->staff->id);
        $returns->receive($request->fresh(), null, $this->staff->id);
        $returns->complete($request->fresh(), $this->staff->id);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $this->shipments->move($shipment->fresh(), ShipmentStatus::RETURNED_TO_SENDER, null, $this->staff->id);

        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity, 'the two units left, not three');
        $this->assertSame(3, (int) StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::RETURN->value)->sum('quantity'));
    }

    public function test_a_transition_the_order_cannot_make_is_refused_and_nothing_changes(): void
    {
        // Preparing goes with the courier before it is delivered.
        $order = $this->orderAt(OrderStatus::PREPARING);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        try {
            $this->shipments->move($shipment, ShipmentStatus::DELIVERED, null, $this->staff->id);
            $this->fail('A preparing order cannot be delivered.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(OrderStatus::PREPARING->getLabel(), $e->errors()['status'][0]);
            $this->assertStringContainsString(OrderStatus::DELIVERED->getLabel(), $e->errors()['status'][0]);
        }

        $this->assertSame(ShipmentStatus::PENDING, $shipment->fresh()->status);
        $this->assertNull($shipment->fresh()->delivered_at);
        $this->assertSame(1, $shipment->events()->count());
        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
    }

    public function test_shipment_transitions_follow_the_rules(): void
    {
        $order = $this->orderAt(OrderStatus::OUT_FOR_DELIVERY);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $this->assertRefused('status', fn () => $this->shipments->move($shipment, ShipmentStatus::RETURNED_TO_SENDER, null, $this->staff->id));
        $this->assertRefused('status', fn () => $this->shipments->move($shipment, ShipmentStatus::PENDING, null, $this->staff->id));

        $this->shipments->move($shipment, ShipmentStatus::DELIVERED, null, $this->staff->id);

        $this->assertRefused('status', fn () => $this->shipments->move($shipment, ShipmentStatus::CANCELLED, null, $this->staff->id));
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status);
    }

    public function test_a_cancelled_shipment_frees_the_order_for_a_new_one(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);
        $first = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        $this->shipments->move($first, ShipmentStatus::CANCELLED, 'Wrong address', $this->staff->id);
        $second = $this->shipments->create($order, ['courier' => 'manual', 'tracking_number' => 'NEW'], $this->staff->id);

        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
        $this->assertSame($second->id, $order->fresh()->shipment?->id);
    }

    public function test_cancelling_the_order_cancels_its_pending_shipment(): void
    {
        $order = $this->orderAt(OrderStatus::PREPARING);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);

        app(OrderService::class)->changeStatus($order, OrderStatus::CANCELLED, $this->staff->id);

        $this->assertSame(ShipmentStatus::CANCELLED, $shipment->fresh()->status);
        $last = $shipment->events()->get()->last();
        $this->assertSame([ShipmentStatus::CANCELLED, __('Order cancelled'), $this->staff->id], [$last->status, $last->note, $last->created_by]);
        $this->assertNull($order->fresh()->shipment);
    }

    public function test_cancelling_the_order_from_its_form_cancels_its_pending_shipment(): void
    {
        $product = $this->stocked(10);
        $order = $this->orderAt(OrderStatus::PREPARING, $product);
        $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);
        $item = $order->items()->first();

        $this->updateOrder($order, [['id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]], ['status' => OrderStatus::CANCELLED->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(ShipmentStatus::CANCELLED, $shipment->fresh()->status);
    }

    public function test_an_order_with_a_shipment_on_its_way_cannot_be_cancelled(): void
    {
        foreach ([ShipmentStatus::PICKED_UP, ShipmentStatus::IN_TRANSIT] as $status) {
            $order = $this->orderAt(OrderStatus::PREPARING);
            $shipment = $this->shipments->create($order, ['courier' => 'manual'], $this->staff->id);
            $this->shipments->move($shipment, $status, null, $this->staff->id);

            $this->assertFalse($order->fresh()->status->canTransitionTo(OrderStatus::CANCELLED), $status->value);
            $this->assertFalse($order->fresh()->status->canTransitionTo(OrderStatus::REJECTED), $status->value);
            $this->assertRefused('status', fn () => app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::CANCELLED, $this->staff->id));
            $this->assertSame($status, $shipment->fresh()->status);
        }
    }

    public function test_the_courier_may_give_the_tracking_number(): void
    {
        app()->instance('test.courier', new class implements CourierGateway
        {
            public function createShipment(Shipment $shipment): ?string
            {
                return 'FROM-COURIER-'.$shipment->order_id;
            }

            public function fetchStatus(Shipment $shipment): ?ShipmentStatus
            {
                return null;
            }
        });
        config(['couriers.couriers' => [...config('couriers.couriers'), ['key' => 'test', 'name' => 'Test', 'gateway' => 'test.courier']]]);

        $order = $this->orderAt(OrderStatus::PREPARING);
        $manual = $this->orderAt(OrderStatus::PREPARING);

        $this->assertSame('FROM-COURIER-'.$order->id, $this->shipments->create($order, ['courier' => 'test', 'tracking_number' => 'TYPED'], $this->staff->id)->tracking_number);
        $this->assertSame('TYPED', $this->shipments->create($manual, ['courier' => 'manual', 'tracking_number' => 'TYPED'], $this->staff->id)->tracking_number);
    }

    /**
     * An order (one line, of $product or a new stocked product) walked to $status.
     */
    private function orderAt(OrderStatus $status, ?Product $product = null, int $quantity = 1, array $overrides = []): Order
    {
        $product ??= $this->stocked(10);
        $this->storeOrder([['product_id' => $product->id, 'quantity' => $quantity]], $overrides)->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $path = match ($status) {
            OrderStatus::PENDING => [],
            OrderStatus::CONFIRMED => [OrderStatus::CONFIRMED],
            OrderStatus::PREPARING => [OrderStatus::CONFIRMED, OrderStatus::PREPARING],
            OrderStatus::OUT_FOR_DELIVERY => [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY],
            OrderStatus::DELIVERED => [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED],
            OrderStatus::CANCELLED => [OrderStatus::CANCELLED],
        };

        foreach ($path as $next) {
            app(OrderService::class)->changeStatus($order, $next, $this->staff->id);
        }

        return $order->fresh();
    }

    private function assertRefused(string $field, callable $call): void
    {
        try {
            $call();
            $this->fail("Expected a refusal on {$field}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
