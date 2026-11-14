<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Items\Entities\Order;

/**
 * A merchant's wholesale (B2B) orders. Never another user's, never a storefront order.
 */
interface WholesaleOrderRepositoryInterface
{
    public function forMerchant(int $userId, int $perPage = 10): LengthAwarePaginator;

    public function findForMerchant(int $userId, string $number): ?Order;

    /**
     * The total of the merchant's open B2B orders: not delivered yet, and not cancelled, rejected
     * or returned (what credit has promised but the ledger has not invoiced).
     */
    public function openTotal(int $userId): float;
}
