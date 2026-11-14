<?php

namespace Modules\Reports\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Items\Enums\OrderStatus;
use Modules\Reports\Repositories\Contracts\CartReportRepositoryInterface;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\SaleSql;
use Modules\Store\Entities\CartReminder;

class CartReportRepository implements CartReportRepositoryInterface
{
    public function summary(ReportFilters $filters, int $abandonedAfterMinutes, CarbonInterface $now): array
    {
        $row = DB::query()
            ->fromSub($this->carts($filters, $abandonedAfterMinutes, $now), 'c')
            ->selectRaw(implode(', ', self::aggregates()))
            ->first();

        return [
            'started' => (int) $row->started,
            'converted' => (int) $row->converted,
            'abandoned' => (int) $row->abandoned,
            'reachable' => (int) $row->reachable,
            'value' => (string) $row->value,
            'reminded' => (int) $row->reminded,
            'recovered' => (int) $row->recovered,
            'recovered_value' => (string) $row->recovered_value,
        ];
    }

    public function table(ReportFilters $filters, int $abandonedAfterMinutes, CarbonInterface $now): Builder
    {
        return DB::query()
            ->fromSub($this->carts($filters, $abandonedAfterMinutes, $now), 'c')
            ->selectRaw(SaleSql::bucket('c.created_at', $filters->interval).' AS bucket')
            ->selectRaw(implode(', ', self::aggregates()))
            ->groupBy('bucket');
    }

    public function reminders(ReportFilters $filters): Collection
    {
        [$start, $end] = $filters->period->bounds();

        return DB::table(SaleSql::table('store', 'cart_reminders').' as r')
            ->where('r.status', CartReminder::SENT)
            ->where('r.sent_at', '>=', $start)
            ->where('r.sent_at', '<', $end)
            ->groupBy('r.channel')
            ->orderBy('r.channel')
            ->selectRaw('r.channel, COUNT(*) AS sent')
            ->get();
    }

    public function conversion(ReportFilters $filters): array
    {
        $rows = DB::query()
            ->fromSub($this->startedCarts($filters), 'x')
            ->groupBy('x.channel')
            ->selectRaw('x.channel, COUNT(*) AS carts, COUNT(*) FILTER (WHERE x.converted) AS converted')
            ->get()
            ->keyBy('channel');

        return collect(['b2c', 'b2b'])->mapWithKeys(fn (string $channel) => [$channel => [
            'carts' => (int) ($rows->get($channel)->carts ?? 0),
            'converted' => (int) ($rows->get($channel)->converted ?? 0),
        ]])->all();
    }

    public function conversionTable(ReportFilters $filters): Builder
    {
        return DB::query()
            ->fromSub($this->startedCarts($filters), 'x')
            ->selectRaw(SaleSql::bucket('x.created_at', $filters->interval).' AS bucket')
            ->selectRaw("COUNT(*) FILTER (WHERE x.channel = 'b2c') AS b2c_carts")
            ->selectRaw("COUNT(*) FILTER (WHERE x.channel = 'b2c' AND x.converted) AS b2c_converted")
            ->selectRaw("COUNT(*) FILTER (WHERE x.channel = 'b2b') AS b2b_carts")
            ->selectRaw("COUNT(*) FILTER (WHERE x.channel = 'b2b' AND x.converted) AS b2b_converted")
            ->groupBy('bucket');
    }

    /**
     * Store and wholesale carts started in the period: created_at, channel, converted.
     */
    private function startedCarts(ReportFilters $filters): Builder
    {
        [$start, $end] = $filters->period->bounds();
        $cart = fn (string $table, string $channel) => DB::table($table.' as c')
            ->where('c.created_at', '>=', $start)
            ->where('c.created_at', '<', $end)
            ->selectRaw("c.created_at, '{$channel}' AS channel, (c.order_id IS NOT NULL OR c.converted_at IS NOT NULL) AS converted");

        return $cart(SaleSql::table('store', 'carts'), 'b2c')->unionAll($cart(SaleSql::table('wholesale', 'carts'), 'b2b'));
    }

    /**
     * One row per store cart started in the period, with what it became.
     */
    private function carts(ReportFilters $filters, int $abandonedAfterMinutes, CarbonInterface $now): Builder
    {
        [$start, $end] = $filters->period->bounds();
        $quiet = $now->copy()->subMinutes(max(0, $abandonedAfterMinutes))->format('Y-m-d H:i:s');
        $lost = [OrderStatus::CANCELLED->value, OrderStatus::REJECTED->value];

        $value = DB::table(SaleSql::table('store', 'cart_items').' as ci')
            ->join(SaleSql::table('items', 'products').' as p', 'p.id', '=', 'ci.product_id')
            ->groupBy('ci.cart_id')
            ->selectRaw('ci.cart_id, COUNT(*) AS lines, SUM(ci.quantity * '.self::priceSql($now).') AS value');

        $reminders = DB::table(SaleSql::table('store', 'cart_reminders').' as r')
            ->where('r.status', CartReminder::SENT)
            ->groupBy('r.cart_id')
            ->selectRaw('r.cart_id, COUNT(*) AS sent');

        $abandoned = "(c.order_id IS NULL AND c.converted_at IS NULL AND COALESCE(v.lines, 0) > 0 AND COALESCE(c.last_activity_at, c.created_at) <= '{$quiet}')";
        $recovered = '(c.order_id IS NOT NULL AND COALESCE(rem.sent, 0) > 0 AND o.id IS NOT NULL AND o.deleted_at IS NULL AND o.status NOT IN ('.implode(', ', $lost).'))';

        return DB::table(SaleSql::table('store', 'carts').' as c')
            ->leftJoinSub($value, 'v', 'v.cart_id', '=', 'c.id')
            ->leftJoinSub($reminders, 'rem', 'rem.cart_id', '=', 'c.id')
            ->leftJoin(SaleSql::table('items', 'orders').' as o', 'o.id', '=', 'c.order_id')
            ->where('c.created_at', '>=', $start)
            ->where('c.created_at', '<', $end)
            ->select('c.id', 'c.created_at')
            ->selectRaw('(c.order_id IS NOT NULL OR c.converted_at IS NOT NULL) AS converted')
            ->selectRaw("{$abandoned} AS abandoned")
            ->selectRaw('(c.contact_consent_at IS NOT NULL) AS consent')
            ->selectRaw('COALESCE(v.value, 0) AS value')
            ->selectRaw('(COALESCE(rem.sent, 0) > 0) AS reminded')
            ->selectRaw("{$recovered} AS recovered")
            ->selectRaw('o.total AS order_total');
    }

    /**
     * @return list<string>
     */
    private static function aggregates(): array
    {
        return [
            'COUNT(*) AS started',
            'COUNT(*) FILTER (WHERE c.converted) AS converted',
            'COUNT(*) FILTER (WHERE c.abandoned) AS abandoned',
            'COUNT(*) FILTER (WHERE c.abandoned AND c.consent) AS reachable',
            'ROUND(COALESCE(SUM(c.value) FILTER (WHERE c.abandoned), 0), 2) AS value',
            'COUNT(*) FILTER (WHERE c.reminded) AS reminded',
            'COUNT(*) FILTER (WHERE c.recovered) AS recovered',
            'ROUND(COALESCE(SUM(c.order_total) FILTER (WHERE c.recovered), 0), 2) AS recovered_value',
        ];
    }

    /**
     * A product's store price at $now, as PricingService computes it (`p` is the product): the sale
     * price when above 0 and below the base price, less the percentage or fixed discount while its
     * window is open (an end date at midnight runs through that day), never below 0, rounded to cents.
     * CartReportTest compares the two.
     */
    public static function priceSql(CarbonInterface $now): string
    {
        $at = "TIMESTAMP '".$now->format('Y-m-d H:i:s')."'";
        $sale = 'CASE WHEN COALESCE(p.sale_price, 0) > 0 AND p.sale_price < p.base_price THEN p.sale_price ELSE p.base_price END';
        $open = "p.discount_type IN ('percentage', 'fixed') AND COALESCE(p.discount_value, 0) > 0"
            ." AND (p.discount_start_date IS NULL OR {$at} >= p.discount_start_date)"
            ." AND (p.discount_end_date IS NULL OR {$at} <= CASE WHEN p.discount_end_date::time = TIME '00:00:00' THEN p.discount_end_date::date + TIME '23:59:59' ELSE p.discount_end_date END)";
        $off = "CASE WHEN p.discount_type = 'percentage' THEN ({$sale}) * LEAST(p.discount_value, 100) / 100 ELSE p.discount_value END";

        return "ROUND(GREATEST(({$sale}) - CASE WHEN {$open} THEN {$off} ELSE 0 END, 0), 2)";
    }
}
