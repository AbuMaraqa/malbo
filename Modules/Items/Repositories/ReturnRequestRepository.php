<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Entities\ReturnRequestLine;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Modules\Items\Repositories\Contracts\ReturnRequestRepositoryInterface;

class ReturnRequestRepository extends BaseEloquentRepository implements ReturnRequestRepositoryInterface
{
    public function __construct(ReturnRequest $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['order' => fn ($order) => $order->withTrashed(), 'order.user', 'user', 'lines']);
    }

    public function findForForm(int $id): ReturnRequest
    {
        /** @var ReturnRequest */
        return $this->query()
            ->with([
                'order' => fn ($order) => $order->withTrashed(),
                'order.user',
                'user',
                'createdBy',
                'lines.orderItem',
                'lines.exchangeProduct.translations',
                'lines.exchangeProduct.parent.translations',
                'lines.exchangeProduct.attributeValues.translations',
                'history.createdBy',
            ])
            ->findOrFail($id);
    }

    public function lockForUpdate(int $id): ReturnRequest
    {
        /** @var ReturnRequest */
        return $this->query()->lockForUpdate()->with(['lines.orderItem'])->findOrFail($id);
    }

    public function forOrder(int $orderId): Collection
    {
        return $this->query()
            ->with(['lines.orderItem', 'lines.exchangeProduct.translations', 'lines.exchangeProduct.parent.translations', 'lines.exchangeProduct.attributeValues.translations', 'history'])
            ->where('order_id', $orderId)
            ->latest('id')
            ->get();
    }

    public function hasOpen(int $orderId): bool
    {
        return $this->query()->where('order_id', $orderId)
            ->whereIn('status', array_map(fn (ReturnRequestStatus $status) => $status->value, ReturnRequestStatus::open()))
            ->exists();
    }

    public function refundedSoFar(int $orderId, ?int $exceptId = null): float
    {
        return (float) $this->query()
            ->where('order_id', $orderId)
            ->where('type', ReturnRequestType::RETURN->value)
            ->whereNotIn('status', array_map(fn (ReturnRequestStatus $status) => $status->value, ReturnRequestStatus::void()))
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->sum('refund_amount');
    }

    public function hasCompletedExchange(int $orderId): bool
    {
        return $this->query()->where('order_id', $orderId)
            ->where('type', ReturnRequestType::EXCHANGE->value)
            ->where('status', ReturnRequestStatus::COMPLETED->value)
            ->exists();
    }

    public function takenQuantities(int $orderId, bool $completedOnly = false): array
    {
        $requests = $this->model->getTable();
        $lines = (new ReturnRequestLine)->getTable();

        return (new ReturnRequestLine)->newQuery()->toBase()
            ->join($requests, "{$requests}.id", '=', "{$lines}.return_request_id")
            ->where("{$requests}.order_id", $orderId)
            ->when(
                $completedOnly,
                fn ($query) => $query->where("{$requests}.status", ReturnRequestStatus::COMPLETED->value),
                fn ($query) => $query->whereNotIn("{$requests}.status", array_map(fn (ReturnRequestStatus $status) => $status->value, ReturnRequestStatus::void())),
            )
            ->groupBy("{$lines}.order_item_id")
            ->selectRaw("{$lines}.order_item_id as item_id, sum({$lines}.quantity) as taken")
            ->pluck('taken', 'item_id')
            ->map(fn ($taken) => (int) $taken)
            ->all();
    }
}
