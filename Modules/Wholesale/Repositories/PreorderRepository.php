<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\PreorderRepositoryInterface;

class PreorderRepository implements PreorderRepositoryInterface
{
    /** Orders whose goods have not reached the merchant and are not closed. */
    private const OPEN = [
        OrderStatus::PENDING,
        OrderStatus::AWAITING_CONFIRMATION,
        OrderStatus::CONFIRMED,
        OrderStatus::PREPARING,
        OrderStatus::READY_FOR_PICKUP,
        OrderStatus::OUT_FOR_DELIVERY,
    ];

    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function tableQuery(): Builder
    {
        $query = $this->products->query();
        $products = $query->getModel()->getTable();
        $items = (new OrderItem)->getTable();

        return $query
            ->select($products.'.*')
            ->selectSub($this->lines()->selectRaw("coalesce(sum({$items}.preorder_quantity), 0)")->whereColumn($items.'.product_id', $products.'.id'), 'preordered')
            ->selectSub($this->lines()->selectRaw("count(distinct {$items}.order_id)")->whereColumn($items.'.product_id', $products.'.id'), 'orders_count')
            ->whereIn($products.'.id', $this->lines()->select($items.'.product_id'))
            ->with(['translations', 'parent.translations', 'attributeValues.translations', 'attributeValues.attribute.translations']);
    }

    public function ordersFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $items = (new OrderItem)->getTable();
        $orders = (new Order)->getTable();

        return $this->lines()
            ->whereIn($items.'.product_id', $productIds)
            ->orderBy($orders.'.id')
            ->get([$items.'.product_id', $items.'.quantity', $items.'.preorder_quantity', $orders.'.id', $orders.'.order_number', $orders.'.customer_name', $orders.'.created_at'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'number' => (string) $row->order_number,
                'shop' => $row->customer_name,
                'quantity' => (int) $row->quantity,
                'preorder' => (int) $row->preorder_quantity,
                'placed_at' => $row->created_at ? substr((string) $row->created_at, 0, 10) : null,
            ])->values()->all())
            ->all();
    }

    /**
     * Pre-ordered lines of open B2B orders that are not deleted.
     */
    private function lines(): QueryBuilder
    {
        $items = (new OrderItem)->getTable();
        $orders = (new Order)->getTable();

        return DB::table($items)
            ->join($orders, $orders.'.id', '=', $items.'.order_id')
            ->where($orders.'.channel', OrderChannel::B2B->value)
            ->whereIn($orders.'.status', array_map(fn (OrderStatus $status) => $status->value, self::OPEN))
            ->whereNull($orders.'.deleted_at')
            ->whereNull($items.'.deleted_at')
            ->where($items.'.preorder_quantity', '>', 0);
    }
}
