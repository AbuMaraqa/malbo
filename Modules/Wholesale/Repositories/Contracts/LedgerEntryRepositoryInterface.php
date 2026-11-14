<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Wholesale\Entities\LedgerEntry;

/**
 * Reads of merchants' accounts. Entries count in date order, then in the order they were entered.
 */
interface LedgerEntryRepositoryInterface extends RepositoryInterface
{
    /**
     * What the merchant owes (the sum of its entries); with $before, only entries dated before it.
     */
    public function balance(int $merchantId, ?string $before = null): float;

    /**
     * The merchant's entries, each with `running_balance` (the balance after it, over the whole
     * account, whatever is filtered later), its order and author loaded. Unordered: the table sorts.
     */
    public function tableQuery(int $merchantId): Builder;

    /**
     * The merchant's entries dated from $from to $to (either may be null), oldest first.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function between(int $merchantId, ?string $from, ?string $to): Collection;

    /**
     * Sum of the amounts per type.
     *
     * @return array<string, float> type value => sum
     */
    public function totalsByType(int $merchantId): array;

    /**
     * The order's invoice amount, or null when it was never invoiced.
     */
    public function invoicedAmount(int $orderId): ?float;

    /**
     * What the order was credited so far (a positive number).
     */
    public function creditedAmount(int $orderId): float;

    public function hasCreditForReturn(int $returnRequestId): bool;

    /**
     * Invoices whose order is not marked paid: delivered, and no payment recorded against it.
     */
    public function unpaidInvoiceCount(int $merchantId): int;

    /**
     * The sum of the entries the platform posted on this order from one of these sources.
     *
     * @param  list<string>  $sources
     */
    public function sumBySource(int $orderId, array $sources): float;

    public function hasSource(int $orderId, string $source): bool;

    /**
     * One of the merchant's entries, or null when it is another merchant's.
     */
    public function findForMerchant(int $merchantId, int $id): ?LedgerEntry;

    /**
     * The merchant row, locked FOR UPDATE: entries and credit checks of one merchant run one at a time.
     */
    public function lockMerchant(int $merchantId): void;
}
