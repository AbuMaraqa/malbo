<?php

namespace Modules\Reports\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Items\Enums\OrderChannel;
use Modules\Reports\Repositories\Contracts\MerchantReportRepositoryInterface;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\SaleSql;

class MerchantReportRepository implements MerchantReportRepositoryInterface
{
    public function table(ReportFilters $filters, string $locale): Builder
    {
        $tiers = SaleSql::table('wholesale', 'tier_translations');

        return DB::table(SaleSql::table('wholesale', 'merchants').' as m')
            ->leftJoinSub($this->sales($filters), 's', 's.user_id', '=', 'm.user_id')
            ->leftJoinSub($this->lastOrders(), 'lo', 'lo.user_id', '=', 'm.user_id')
            ->leftJoinSub($this->balances(), 'b', 'b.merchant_id', '=', 'm.id')
            ->leftJoin('users as rep', 'rep.id', '=', 'm.sales_rep_id')
            ->select('m.id as row_key', 'm.shop_name', 'm.status', 'm.tier_id', 'm.sales_rep_id', 'rep.name as sales_rep', 'm.credit_limit', 'lo.last_order_at')
            ->selectRaw("(SELECT tt.name FROM {$tiers} tt WHERE tt.tier_id = m.tier_id ORDER BY (tt.locale = '{$locale}') DESC, (tt.locale = 'ar') DESC, tt.id LIMIT 1) AS tier")
            ->selectRaw('COALESCE(s.orders, 0) AS orders')
            ->selectRaw('COALESCE(s.units, 0) AS units')
            ->selectRaw('ROUND(COALESCE(s.net, 0), 2) AS net')
            ->selectRaw('CASE WHEN s.orders > 0 THEN ROUND(s.ordered / s.orders, 2) END AS aov')
            ->selectRaw('ROUND(COALESCE(b.balance, 0), 2) AS balance');
    }

    public function totals(Builder $rows): array
    {
        $row = DB::query()
            ->fromSub($rows, 't')
            ->selectRaw('COUNT(*) AS merchants, COUNT(*) FILTER (WHERE t.orders > 0) AS active, COALESCE(SUM(t.orders), 0) AS orders')
            ->selectRaw('ROUND(COALESCE(SUM(t.net), 0), 2) AS net, ROUND(COALESCE(SUM(t.balance), 0), 2) AS balance')
            ->first();

        return [
            'merchants' => (int) $row->merchants,
            'active' => (int) $row->active,
            'orders' => (int) $row->orders,
            'net' => (string) $row->net,
            'balance' => (string) $row->balance,
        ];
    }

    /**
     * Each merchant account's B2B sales in the period.
     */
    private function sales(ReportFilters $filters): Builder
    {
        return DB::query()
            ->fromSub(SaleSql::orders($filters->withChannel(OrderChannel::B2B)), 'so')
            ->whereNotNull('so.user_id')
            ->groupBy('so.user_id')
            ->select('so.user_id')
            ->selectRaw('COUNT(*) AS orders, SUM(so.units) AS units')
            ->selectRaw('SUM(so.gross - so.discount - so.returns) AS net, SUM(so.gross - so.discount) AS ordered');
    }

    /**
     * Each merchant account's last B2B sale, whatever the period.
     */
    private function lastOrders(): Builder
    {
        return DB::table(SaleSql::table('items', 'orders').' as o')
            ->whereNull('o.deleted_at')
            ->where('o.channel', OrderChannel::B2B->value)
            ->whereIn('o.status', SaleSql::statuses())
            ->whereNotNull('o.user_id')
            ->groupBy('o.user_id')
            ->selectRaw('o.user_id, MAX(o.created_at) AS last_order_at');
    }

    private function balances(): Builder
    {
        return DB::table(SaleSql::table('wholesale', 'ledger_entries').' as le')
            ->groupBy('le.merchant_id')
            ->selectRaw('le.merchant_id, SUM(le.amount) AS balance');
    }
}
