<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Shipment;

interface ShipmentRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query with what its rows show.
     */
    public function tableQuery(): Builder;

    /**
     * The shipment locked for the rest of the transaction.
     */
    public function lockForUpdate(int $id): Shipment;

    /**
     * The order's shipment that is not cancelled, or null.
     */
    public function activeForOrder(int $orderId): ?Shipment;

    /**
     * The order's shipment that is not cancelled, locked for the rest of the transaction, or null.
     * Lock the order first.
     */
    public function lockActiveForOrder(int $orderId): ?Shipment;

    /**
     * The order's active shipment with its events and who made them (the order page's card), or null.
     */
    public function forOrderPage(int $orderId): ?Shipment;
}
