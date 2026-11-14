<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Order;

interface OrderRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query with what its rows show.
     */
    public function tableQuery(): Builder;

    /**
     * An order with what its form shows.
     */
    public function findForForm(int $id): Order;

    /**
     * An order locked for the rest of the transaction, with its items: two people changing its status
     * at once must each check the transition against what the other saved.
     */
    public function lockForUpdate(int $id): Order;

    /**
     * A storefront (B2C) order by its number, with what the tracking page shows (lines, status
     * history, the active shipment), or null. Wholesale orders are followed in the portal.
     */
    public function findForTracking(string $number): ?Order;

    /**
     * Any order by its number (either channel), loaded as findForTracking loads it: staff lookups.
     */
    public function findByNumber(string $number): ?Order;

    /**
     * These orders (not deleted) with what the invoice and the waybill print, in id order.
     *
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Order>
     */
    public function forDocuments(array $ids): EloquentCollection;

    /**
     * The orders that count as uses of this coupon code, by who placed them: every order that used
     * it, except cancelled or rejected ones (soft-deleted orders do not count either).
     *
     * @return list<array{phone: ?string, user_id: ?int}>
     */
    public function couponUses(string $code): array;

    /**
     * Units already given back to stock as returns on this order (completed return requests).
     *
     * @return array<int, int> product id => quantity
     */
    public function returnedQuantities(int $orderId): array;

    /**
     * Whether any return or exchange request (of any status) was made on the order.
     */
    public function hasReturnRequests(int $orderId): bool;

    /**
     * A storefront account's orders (its own user_id only), the newest first. A guest order joins
     * an account only when its buyer claims it with its number and phone (AccountOrders::claim).
     */
    public function forCustomer(int $userId, int $perPage = 10): LengthAwarePaginator;

    /**
     * One of the account's orders by its number, with its lines and status history, or null.
     */
    public function findForCustomer(int $userId, string $number): ?Order;
}
