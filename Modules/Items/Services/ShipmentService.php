<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ShipmentRepositoryInterface;
use Modules\Items\Services\Couriers\CourierRegistry;

/**
 * Writes shipments. A shipment is made for an order that is on its way (preparing, ready or with
 * the courier, to be delivered; a confirmed order is not packed yet), one at a time. Moving it moves the order through
 * OrderService::changeStatus, the only writer of orders: picked up or in transit → with courier,
 * delivered → delivered, returned to sender → returned (the stock comes back by the order's rules).
 * A move the order cannot make is refused before anything is written.
 *
 * Locks: the order first, then the shipment, as everywhere an order and its shipment change.
 */
class ShipmentService
{
    /** Orders a shipment can be made for: being prepared, or further, until it reaches the buyer. */
    private const SHIPPABLE = [OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP, OrderStatus::OUT_FOR_DELIVERY];

    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderService $orderService,
        private readonly CourierRegistry $couriers,
    ) {}

    /**
     * Whether a shipment can be made for the order as it is (its status and how it is delivered;
     * not whether it already has one).
     */
    public static function shippable(Order $order): bool
    {
        return self::delivered($order) && in_array($order->status, self::SHIPPABLE, true);
    }

    /**
     * A delivered order (an old order without a delivery type counts as one).
     */
    private static function delivered(Order $order): bool
    {
        return $order->delivery_type === null || $order->delivery_type === DeliveryType::DELIVERY;
    }

    /**
     * @param  array{courier: string, tracking_number?: ?string, cost?: float|int|string|null, notes?: ?string}  $data
     *
     * @throws ValidationException on `courier` (not listed) or `order_id` (not on its way, or already shipped)
     */
    public function create(Order $order, array $data, int $userId): Shipment
    {
        try {
            $gateway = $this->couriers->gateway((string) $data['courier']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['courier' => __('Choose a courier from the list.')]);
        }

        return DB::transaction(function () use ($order, $data, $userId, $gateway): Shipment {
            $locked = $this->orders->lockForUpdate($order->id);

            if (! self::delivered($locked)) {
                throw ValidationException::withMessages(['order_id' => __('This order is not delivered: it has no shipment.')]);
            }

            if (! in_array($locked->status, self::SHIPPABLE, true)) {
                throw ValidationException::withMessages(['order_id' => __('A shipment can be made once the order is being prepared, until it reaches the buyer. This order is :status.', ['status' => $locked->status->getLabel()])]);
            }

            if ($this->shipments->activeForOrder($locked->id) !== null) {
                throw ValidationException::withMessages(['order_id' => __('This order already has a shipment. Cancel it to make another.')]);
            }

            /** @var Shipment $shipment */
            $shipment = $this->shipments->create([
                'order_id' => $locked->id,
                'courier' => (string) $data['courier'],
                'tracking_number' => self::text($data['tracking_number'] ?? null),
                'status' => ShipmentStatus::PENDING,
                'cost' => round((float) ($data['cost'] ?? 0), 2),
                'notes' => self::text($data['notes'] ?? null),
                'created_by' => $userId,
            ]);

            $tracking = $gateway->createShipment($shipment);

            if ($tracking !== null && $tracking !== $shipment->tracking_number) {
                $shipment->update(['tracking_number' => $tracking]);
            }

            $this->recordEvent($shipment, ShipmentStatus::PENDING, null, $userId);

            return $shipment;
        });
    }

    /**
     * @throws ValidationException on `status`: a move the shipment or its order cannot make
     */
    public function move(Shipment $shipment, ShipmentStatus $to, ?string $note, int $userId): Shipment
    {
        return DB::transaction(function () use ($shipment, $to, $note, $userId): Shipment {
            $order = $this->orders->lockForUpdate($shipment->order_id);
            $locked = $this->shipments->lockForUpdate($shipment->id);

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => __('A shipment that is :current cannot become :new.', [
                    'current' => $locked->status->getLabel(),
                    'new' => $to->getLabel(),
                ])]);
            }

            $target = $to->orderStatus();
            $orderMoves = $target !== null && $order->status !== $target;

            if ($orderMoves && ! $order->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => __('The order is :current: it cannot become :new. Change the order\'s status first.', [
                    'current' => $order->status->getLabel(),
                    'new' => $target->getLabel(),
                ])]);
            }

            $locked->fill([
                'status' => $to,
                'shipped_at' => in_array($to, [ShipmentStatus::PICKED_UP, ShipmentStatus::IN_TRANSIT], true) ? ($locked->shipped_at ?? now()) : $locked->shipped_at,
                'delivered_at' => $to === ShipmentStatus::DELIVERED ? now() : $locked->delivered_at,
            ])->save();

            $this->recordEvent($locked, $to, $note, $userId);

            if ($orderMoves) {
                $this->orderService->changeStatus($order, $target, $userId);
            }

            $shipment->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /**
     * The order was cancelled or rejected (OrderStatusChanged, inside OrderService's transaction,
     * the order already locked): its pending shipment is cancelled too, with an event saying why.
     * A shipment past pending is left alone: its order is with the courier and cannot be cancelled.
     */
    public function cancelPendingFor(Order $order, ?int $userId): void
    {
        DB::transaction(function () use ($order, $userId): void {
            $shipment = $this->shipments->lockActiveForOrder($order->id);

            if ($shipment === null || $shipment->status !== ShipmentStatus::PENDING) {
                return;
            }

            $shipment->update(['status' => ShipmentStatus::CANCELLED]);
            $this->recordEvent($shipment, ShipmentStatus::CANCELLED, __('Order cancelled'), $userId);
        });
    }

    private function recordEvent(Shipment $shipment, ShipmentStatus $status, ?string $note, ?int $userId): void
    {
        $shipment->events()->create([
            'status' => $status,
            'note' => self::text($note),
            'at' => now(),
            'created_by' => $userId,
        ]);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
