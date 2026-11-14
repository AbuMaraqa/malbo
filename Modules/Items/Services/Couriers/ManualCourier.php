<?php

namespace Modules\Items\Services\Couriers;

use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\ShipmentStatus;

/**
 * MALBO's own delivery, or a courier with no integration: staff type the tracking number and move
 * the status by hand. Nothing leaves the system.
 */
class ManualCourier implements CourierGateway
{
    public function createShipment(Shipment $shipment): ?string
    {
        return null;
    }

    public function fetchStatus(Shipment $shipment): ?ShipmentStatus
    {
        return null;
    }
}
