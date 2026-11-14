<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Repositories\Contracts\ShipmentRepositoryInterface;

class ShipmentRepository extends BaseEloquentRepository implements ShipmentRepositoryInterface
{
    public function __construct(Shipment $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['order' => fn ($order) => $order->withTrashed(), 'order.user', 'createdBy']);
    }

    public function lockForUpdate(int $id): Shipment
    {
        /** @var Shipment */
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function activeForOrder(int $orderId): ?Shipment
    {
        /** @var Shipment|null */
        return $this->query()
            ->where('order_id', $orderId)
            ->where('status', '!=', ShipmentStatus::CANCELLED->value)
            ->latest('id')
            ->first();
    }

    public function lockActiveForOrder(int $orderId): ?Shipment
    {
        /** @var Shipment|null */
        return $this->query()
            ->where('order_id', $orderId)
            ->where('status', '!=', ShipmentStatus::CANCELLED->value)
            ->lockForUpdate()
            ->latest('id')
            ->first();
    }

    public function forOrderPage(int $orderId): ?Shipment
    {
        /** @var Shipment|null */
        return $this->query()
            ->with(['events.createdBy', 'createdBy'])
            ->where('order_id', $orderId)
            ->where('status', '!=', ShipmentStatus::CANCELLED->value)
            ->latest('id')
            ->first();
    }
}
