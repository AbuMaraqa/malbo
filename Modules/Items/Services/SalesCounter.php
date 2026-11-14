<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Events\ProductSalesChanged;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;

/**
 * The only code that writes `sales_count`: units sold per product, for the shop's "best selling"
 * sort. A product counts the units on the lines of store orders (B2C, never wholesale) that are
 * not deleted and still hold their sale (not cancelled, rejected or returned); a variant's units
 * count on its parent. A return request that leaves the order delivered does not
 * lower it; the order reaching RETURNED does. OrderService moves it with every order write, in the
 * order's transaction; recount() rebuilds it from the orders (`items:recount-sales`).
 */
class SalesCounter
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * A store order (B2C) in this status counts its lines; a wholesale order never does: a merchant's
     * volume must not decide what the store shows as its best sellers.
     */
    public static function counts(Order $order, OrderStatus $status): bool
    {
        return $order->channel !== OrderChannel::B2B && ! $status->releasesStock();
    }

    /**
     * Moves the counts from what an order counted ($before) to what it counts now ($after), each
     * product id => units. Parents in id order, one row at a time: two orders never wait on each
     * other in a circle. Never below 0.
     *
     * @param  array<int, int>  $before
     * @param  array<int, int>  $after
     */
    public function move(array $before, array $after): void
    {
        $changes = [];

        foreach (array_keys($before + $after) as $productId) {
            $change = (int) ($after[$productId] ?? 0) - (int) ($before[$productId] ?? 0);

            if ($change !== 0) {
                $changes[(int) $productId] = $change;
            }
        }

        if ($changes === []) {
            return;
        }

        $parents = [];

        foreach ($this->products->countedProductIds(array_keys($changes)) as $productId => $parentId) {
            $parents[$parentId] = ($parents[$parentId] ?? 0) + $changes[$productId];
        }

        $parents = array_filter($parents);
        ksort($parents);

        foreach ($parents as $parentId => $change) {
            $this->products->query()->withTrashed()->toBase()->where('id', $parentId)
                ->update(['sales_count' => DB::raw('greatest(sales_count + ('.(int) $change.'), 0)')]);
        }

        if ($parents !== []) {
            event(new ProductSalesChanged(array_keys($parents)));
        }
    }

    /**
     * Rebuilds every product's count from the orders.
     *
     * @return int products whose count changed
     */
    public function recount(): int
    {
        return DB::transaction(function (): int {
            $prefix = config('items.table_prefix');
            $released = implode(', ', array_map(fn (OrderStatus $status) => $status->value, array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->releasesStock())));
            $wholesale = OrderChannel::B2B->value;

            // No order may change while the counts are rebuilt from them: a sale meanwhile would be lost.
            DB::statement("lock table {$prefix}orders in share mode");

            $changed = DB::update(<<<SQL
                update {$prefix}products as target
                set sales_count = coalesce(sold.units, 0)
                from {$prefix}products as source
                left join (
                    select coalesce(lp.parent_id, lp.id) as product_id, sum(oi.quantity) as units
                    from {$prefix}order_items as oi
                    join {$prefix}orders as o on o.id = oi.order_id
                    join {$prefix}products as lp on lp.id = oi.product_id
                    where oi.deleted_at is null and o.deleted_at is null and o.status not in ({$released})
                        and coalesce(o.channel, 'b2c') <> '{$wholesale}'
                    group by 1
                ) as sold on sold.product_id = source.id
                where target.id = source.id and target.sales_count is distinct from coalesce(sold.units, 0)
                SQL);

            event(new ProductSalesChanged([]));

            return $changed;
        });
    }
}
