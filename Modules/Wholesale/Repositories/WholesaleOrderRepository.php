<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleOrderRepositoryInterface;

class WholesaleOrderRepository implements WholesaleOrderRepositoryInterface
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    public function forMerchant(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->orders->query()
            ->where('user_id', $userId)
            ->where('channel', OrderChannel::B2B->value)
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->latest()->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForMerchant(int $userId, string $number): ?Order
    {
        /** @var Order|null */
        return $this->orders->query()
            ->where('user_id', $userId)
            ->where('channel', OrderChannel::B2B->value)
            ->where('order_number', $number)
            ->with(['items' => fn ($items) => $items->orderBy('id'), 'statusHistory', 'shipment'])
            ->first();
    }

    public function openTotal(int $userId): float
    {
        $open = [OrderStatus::PENDING, OrderStatus::AWAITING_CONFIRMATION, OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP, OrderStatus::OUT_FOR_DELIVERY];

        return round((float) $this->orders->query()
            ->where('user_id', $userId)
            ->where('channel', OrderChannel::B2B->value)
            ->whereIn('status', array_map(fn (OrderStatus $status) => $status->value, $open))
            ->sum('total'), 2);
    }
}
