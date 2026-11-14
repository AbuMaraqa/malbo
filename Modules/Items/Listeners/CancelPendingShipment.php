<?php

namespace Modules\Items\Listeners;

use Modules\Items\Enums\OrderStatus;
use Modules\Items\Events\OrderStatusChanged;
use Modules\Items\Services\ShipmentService;

/**
 * A cancelled or rejected order takes its pending shipment with it. Synchronous, in the order's
 * transaction (the order is locked first, then the shipment). A shipment past pending never meets
 * a cancellable order: once picked up the order is with the courier, which cannot be cancelled.
 */
class CancelPendingShipment
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (in_array($event->to, [OrderStatus::CANCELLED, OrderStatus::REJECTED], true)) {
            $this->shipments->cancelPendingFor($event->order, $event->userId);
        }
    }
}
