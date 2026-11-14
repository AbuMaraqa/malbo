<?php

namespace Modules\Items\Services\Couriers;

use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\ShipmentStatus;

/**
 * What a courier integration does for MALBO. A gateway is named per courier in
 * config/couriers.php; only the built-in manual courier exists today, and a real integration
 * (an API call) implements this contract.
 *
 * Rule for a real HTTP courier: call it after the database commit (DB::afterCommit, or a job
 * dispatched after commit), never inside ShipmentService's transaction, and send the shipment id
 * as the idempotency key, so a retry never books the parcel twice. ShipmentService calls
 * createShipment() inside the transaction today only because the manual courier makes no call.
 */
interface CourierGateway
{
    /**
     * Registers the shipment with the courier, once it is saved. Returns the courier's tracking
     * number, or null to keep the one staff typed.
     */
    public function createShipment(Shipment $shipment): ?string;

    /**
     * The shipment's status as the courier knows it, or null when the courier cannot tell (staff
     * update it by hand).
     */
    public function fetchStatus(Shipment $shipment): ?ShipmentStatus;
}
