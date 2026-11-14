<?php

namespace Modules\Reports\Repositories\Contracts;

use Illuminate\Database\Query\Builder;
use Modules\Reports\Support\ReportFilters;

/**
 * Wholesale merchants and their B2B sales (Reports\Support\SaleSql), read only.
 */
interface MerchantReportRepositoryInterface
{
    /**
     * One row per merchant: row_key (id), shop_name, status, tier_id, tier, sales_rep_id, sales_rep,
     * orders, units, net, aov (null without orders) in the filters' period; last_order_at (all
     * time); balance (the ledger's sum) and credit_limit.
     */
    public function table(ReportFilters $filters, string $locale): Builder;

    /**
     * What some rows of table() add up to (the rows the page shows, its filters applied).
     *
     * @return array{merchants: int, active: int, orders: int, net: string, balance: string}
     */
    public function totals(Builder $rows): array;
}
