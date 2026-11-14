<?php

namespace Modules\Items\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;

class OrderRepository extends BaseEloquentRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['user', 'branch.translations', 'items.product.translations']);
    }

    public function findForForm(int $id): Order
    {
        /** @var Order */
        return $this->query()
            ->with(['user', 'items' => fn ($items) => $items->orderBy('id'), 'items.product.translations', 'items.addonOptions', 'statusHistory.createdBy'])
            ->findOrFail($id);
    }

    public function lockForUpdate(int $id): Order
    {
        /** @var Order */
        return $this->query()->lockForUpdate()->with(['items.addonOptions'])->findOrFail($id);
    }

    public function findForTracking(string $number): ?Order
    {
        /** @var Order|null */
        return $this->query()->with(['items' => fn ($items) => $items->orderBy('id'), 'statusHistory', 'shipment'])
            ->where('order_number', $number)
            // Guests track storefront orders only: a merchant follows a wholesale order in the portal.
            ->where('channel', OrderChannel::B2C->value)
            ->first();
    }

    public function findByNumber(string $number): ?Order
    {
        /** @var Order|null */
        return $this->query()->with(['items' => fn ($items) => $items->orderBy('id'), 'statusHistory', 'shipment'])
            ->where('order_number', $number)
            ->first();
    }

    public function forDocuments(array $ids): EloquentCollection
    {
        return $this->query()
            ->with(['items' => fn ($items) => $items->orderBy('id'), 'city.translations', 'district.translations', 'user', 'shipment'])
            ->whereKey($ids)
            ->orderBy('id')
            ->get();
    }

    public function couponUses(string $code): array
    {
        return $this->query()->toBase()
            ->where('coupon_code', $code)
            ->whereNull('deleted_at')
            ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::REJECTED->value])
            ->get(['contact_phone', 'user_id'])
            ->map(fn (object $row) => ['phone' => $row->contact_phone, 'user_id' => $row->user_id !== null ? (int) $row->user_id : null])
            ->all();
    }

    public function returnedQuantities(int $orderId): array
    {
        return StockMovement::query()->toBase()
            ->where('order_id', $orderId)
            ->where('type', StockMovementType::RETURN->value)
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity) as returned')
            ->pluck('returned', 'product_id')
            ->map(fn ($returned) => (int) $returned)
            ->all();
    }

    public function hasReturnRequests(int $orderId): bool
    {
        return ReturnRequest::query()->where('order_id', $orderId)->exists();
    }

    public function forCustomer(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()->where('user_id', $userId)
            ->withCount('items')
            ->latest()->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCustomer(int $userId, string $number): ?Order
    {
        /** @var Order|null */
        return $this->query()->where('user_id', $userId)
            ->with(['items' => fn ($items) => $items->orderBy('id'), 'items.product', 'statusHistory', 'shipment'])
            ->where('order_number', $number)
            ->first();
    }
}
