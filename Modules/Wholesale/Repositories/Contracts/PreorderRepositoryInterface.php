<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pre-orders: lines of open B2B orders that reserved incoming goods (`preorder_quantity` > 0), read
 * per product. An order is open until it is delivered or closed (cancelled, rejected, returned,
 * exchanged).
 */
interface PreorderRepositoryInterface
{
    /**
     * Sellable products with pre-ordered pieces: each with `preordered` (pieces over open orders) and
     * `orders_count`, its incoming goods and stock, its names and options loaded.
     */
    public function tableQuery(): Builder;

    /**
     * The open orders that pre-ordered each product, oldest first.
     *
     * @param  list<int>  $productIds
     * @return array<int, list<array{id: int, number: string, shop: ?string, quantity: int, preorder: int, placed_at: ?string}>>
     */
    public function ordersFor(array $productIds): array;
}
