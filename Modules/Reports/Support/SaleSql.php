<?php

namespace Modules\Reports\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;

/**
 * What every report counts as a sale, in SQL, so the reports agree with each other.
 *
 * - A sale is an order (not deleted) MALBO confirmed: confirmed, preparing, ready for pickup, with
 *   the courier, delivered, returned or exchanged. New and awaiting-confirmation orders are not
 *   sales yet; cancelled and rejected ones never were. Its date is when it was placed.
 * - A line's share of the order discount: its total × the order's discount ÷ the order's subtotal
 *   (as refunds compute it, ReturnRequestService::unitDiscount).
 * - Returned units: all of a line when the order is RETURNED, else the units of completed return
 *   requests; exchanged units: those of completed exchange requests. Returns are valued at the
 *   line's price after its discount share (a bundle piece's price is its allocated share).
 * - Net = gross − discounts − returns. Money stays numeric until a report rounds its sums.
 */
final class SaleSql
{
    public const STATUSES = [
        OrderStatus::CONFIRMED,
        OrderStatus::PREPARING,
        OrderStatus::READY_FOR_PICKUP,
        OrderStatus::OUT_FOR_DELIVERY,
        OrderStatus::DELIVERED,
        OrderStatus::RETURNED,
        OrderStatus::EXCHANGED,
    ];

    /** Placed but not confirmed yet: shown beside the sales, never in them. */
    public const PENDING = [OrderStatus::PENDING, OrderStatus::AWAITING_CONFIRMATION];

    /**
     * @return list<int>
     */
    public static function statuses(): array
    {
        return array_map(fn (OrderStatus $status) => $status->value, self::STATUSES);
    }

    public static function table(string $module, string $table): string
    {
        return config($module.'.table_prefix').$table;
    }

    /**
     * Sale orders of the filters: status, period (on `created_at`), channel and branch.
     */
    public static function scopeOrders(Builder $query, ReportFilters $filters, string $alias = 'o', ?array $statuses = null): Builder
    {
        [$start, $end] = $filters->period->bounds();

        return $query
            ->whereNull("{$alias}.deleted_at")
            ->whereIn("{$alias}.status", $statuses ?? self::statuses())
            ->where("{$alias}.created_at", '>=', $start)
            ->where("{$alias}.created_at", '<', $end)
            ->when($filters->channel, fn (Builder $query, $channel) => $query->where("{$alias}.channel", $channel->value))
            ->when($filters->branchId, fn (Builder $query, int $branch) => $query->where("{$alias}.branch_id", $branch));
    }

    /**
     * Units of completed requests per order line: returned (return requests) and exchanged.
     */
    public static function returnsByItem(): Builder
    {
        return DB::table(self::table('items', 'return_request_lines').' as rl')
            ->join(self::table('items', 'return_requests').' as r', 'r.id', '=', 'rl.return_request_id')
            ->where('r.status', ReturnRequestStatus::COMPLETED->value)
            ->groupBy('rl.order_item_id')
            ->select('rl.order_item_id')
            ->selectRaw('COALESCE(SUM(rl.quantity) FILTER (WHERE r.type = ?), 0) AS returned', [ReturnRequestType::RETURN->value])
            ->selectRaw('COALESCE(SUM(rl.quantity) FILTER (WHERE r.type = ?), 0) AS exchanged', [ReturnRequestType::EXCHANGE->value]);
    }

    /**
     * One row per line of a sale order: what it sold, its discount share, what came back.
     * With $categories (ids), only lines whose product (its parent for a variant) is in one of them.
     *
     * @param  list<int>|null  $categories
     */
    public static function lines(ReportFilters $filters, ?array $categories = null): Builder
    {
        $returned = 'CASE WHEN o.status = '.OrderStatus::RETURNED->value.' THEN oi.quantity ELSE LEAST(oi.quantity, COALESCE(rr.returned, 0)) END';
        $exchanged = 'CASE WHEN o.status = '.OrderStatus::RETURNED->value.' THEN 0 ELSE LEAST(oi.quantity - LEAST(oi.quantity, COALESCE(rr.returned, 0)), COALESCE(rr.exchanged, 0)) END';
        $ratio = 'CASE WHEN o.sub_total > 0 THEN LEAST(o.discount, o.sub_total) / o.sub_total ELSE 0 END';
        $discount = "oi.total_price * ({$ratio})";

        $query = DB::table(self::table('items', 'order_items').' as oi')
            ->join(self::table('items', 'orders').' as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin(self::table('items', 'products').' as p', 'p.id', '=', 'oi.product_id')
            ->leftJoinSub(self::returnsByItem(), 'rr', 'rr.order_item_id', '=', 'oi.id')
            ->whereNull('oi.deleted_at')
            ->select([
                'oi.id as line_id', 'oi.order_id', 'oi.product_id', 'oi.sku', 'oi.product_name', 'oi.options',
                'oi.bundle_product_id', 'oi.bundle_group', 'oi.bundle', 'oi.quantity',
                'o.created_at', 'o.channel', 'o.user_id', 'o.status',
            ])
            ->selectRaw('COALESCE(p.parent_id, oi.product_id) AS item_id')
            ->selectRaw('oi.total_price AS gross')
            ->selectRaw("{$discount} AS discount")
            ->selectRaw("{$returned} AS returned_units")
            ->selectRaw("{$exchanged} AS exchanged_units")
            ->selectRaw("CASE WHEN oi.quantity > 0 THEN ({$returned}) * (oi.total_price - {$discount}) / oi.quantity ELSE 0 END AS returns")
            ->selectRaw('COALESCE(rr.returned, 0) + COALESCE(rr.exchanged, 0) AS requested_units');

        self::scopeOrders($query, $filters);

        if ($categories !== null) {
            $query->whereExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from(self::table('items', 'products_categories').' as pcf')
                ->whereRaw('pcf.product_id = COALESCE(p.parent_id, oi.product_id)')
                ->whereIn('pcf.category_id', $categories ?: [0]));
        }

        return $query;
    }

    /**
     * One row per sale order: its money (order level, counted once whatever its lines) and what its
     * lines add up to (units, returns).
     */
    public static function orders(ReportFilters $filters): Builder
    {
        $lines = DB::query()
            ->fromSub(self::lines($filters), 'l')
            ->groupBy('l.order_id')
            ->select('l.order_id')
            ->selectRaw('SUM(l.quantity) AS units')
            ->selectRaw('SUM(l.returns) AS returns')
            ->selectRaw('SUM(l.returned_units) AS returned_units');

        $query = DB::table(self::table('items', 'orders').' as o')
            ->leftJoinSub($lines, 'ol', 'ol.order_id', '=', 'o.id')
            ->select(['o.id', 'o.created_at', 'o.channel', 'o.user_id', 'o.total'])
            ->selectRaw('o.sub_total AS gross')
            ->selectRaw('LEAST(o.discount, o.sub_total) AS discount')
            // Returned without ever being delivered (refused at the door): still an order, but no
            // delivery fee earned. One delivered and then sent back keeps its fee (refunds never
            // include it).
            ->selectRaw('CASE WHEN o.status = ? AND NOT EXISTS (SELECT 1 FROM '.self::table('items', 'order_status_history').' h WHERE h.order_id = o.id AND h.status = ?) THEN 0 ELSE o.delivery_fee END AS delivery', [OrderStatus::RETURNED->value, OrderStatus::DELIVERED->value])
            ->selectRaw('COALESCE(ol.units, 0) AS units')
            ->selectRaw('COALESCE(ol.returns, 0) AS returns')
            ->selectRaw('COALESCE(ol.returned_units, 0) AS returned_units');

        return self::scopeOrders($query, $filters);
    }

    /**
     * A timestamp column's day (`YYYY-MM-DD`) or month (`YYYY-MM`) in the reports' timezone.
     * Both zones are checked identifiers, so they are written into the SQL.
     */
    public static function bucket(string $column, string $interval): string
    {
        $stored = ReportPeriod::storedTimezone();
        $local = ReportPeriod::timezone();
        $format = $interval === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';

        return "to_char(({$column} AT TIME ZONE '{$stored}') AT TIME ZONE '{$local}', '{$format}')";
    }

    /**
     * The aggregate columns of a group of sale lines (`lines()` rows aliased `l`).
     *
     * @return list<string>
     */
    public static function lineAggregates(string $alias = 'l'): array
    {
        return [
            "COUNT(DISTINCT {$alias}.order_id) AS orders",
            "COALESCE(SUM({$alias}.quantity), 0) AS units",
            "ROUND(COALESCE(SUM({$alias}.gross), 0), 2) AS gross",
            "ROUND(COALESCE(SUM({$alias}.discount), 0), 2) AS discounts",
            "ROUND(COALESCE(SUM({$alias}.returns), 0), 2) AS returns",
            "ROUND(COALESCE(SUM({$alias}.gross - {$alias}.discount - {$alias}.returns), 0), 2) AS net",
            "COALESCE(SUM({$alias}.returned_units), 0) AS returned_units",
            "COALESCE(SUM({$alias}.exchanged_units), 0) AS exchanged_units",
        ];
    }

    /**
     * A jsonb {locale: text} map read in $locale, then Arabic, then English.
     */
    public static function localized(string $column, string $locale): string
    {
        return "COALESCE({$column}->>'{$locale}', {$column}->>'ar', {$column}->>'en')";
    }

    /**
     * The aggregate columns of a group of sale orders (`orders()` rows aliased `so`).
     *
     * @return list<string>
     */
    public static function orderAggregates(string $alias = 'so'): array
    {
        return [
            "COUNT({$alias}.id) AS orders",
            "COALESCE(SUM({$alias}.units), 0) AS units",
            "ROUND(COALESCE(SUM({$alias}.gross), 0), 2) AS gross",
            "ROUND(COALESCE(SUM({$alias}.discount), 0), 2) AS discounts",
            "ROUND(COALESCE(SUM({$alias}.delivery), 0), 2) AS delivery",
            "ROUND(COALESCE(SUM({$alias}.returns), 0), 2) AS returns",
            "ROUND(COALESCE(SUM({$alias}.gross - {$alias}.discount - {$alias}.returns), 0), 2) AS net",
            "ROUND(COALESCE(SUM({$alias}.gross - {$alias}.discount - {$alias}.returns + {$alias}.delivery), 0), 2) AS total",
            "COALESCE(SUM({$alias}.returned_units), 0) AS returned_units",
        ];
    }
}
