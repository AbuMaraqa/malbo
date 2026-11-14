<?php

namespace Modules\Reports\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\SaleSql;

class SalesReportRepository implements SalesReportRepositoryInterface
{
    public function summary(ReportFilters $filters): array
    {
        $row = (array) DB::query()
            ->fromSub(SaleSql::orders($filters), 'so')
            ->selectRaw(implode(', ', SaleSql::orderAggregates()))
            ->first();

        return self::cast($row);
    }

    public function pending(ReportFilters $filters): array
    {
        $statuses = array_map(fn (OrderStatus $status) => $status->value, SaleSql::PENDING);
        $query = DB::table(SaleSql::table('items', 'orders').' as o')
            ->selectRaw('COUNT(*) AS orders, ROUND(COALESCE(SUM(o.sub_total - LEAST(o.discount, o.sub_total)), 0), 2) AS value');

        $row = SaleSql::scopeOrders($query, $filters, 'o', $statuses)->first();

        return ['orders' => (int) $row->orders, 'value' => (string) $row->value];
    }

    public function timeline(ReportFilters $filters, bool $byChannel = false): Collection
    {
        return $this->timelineTable($filters, $byChannel)->orderBy('bucket')->get();
    }

    public function timelineTable(ReportFilters $filters, bool $byChannel = false): Builder
    {
        return DB::query()
            ->fromSub(SaleSql::orders($filters), 'so')
            ->selectRaw(SaleSql::bucket('so.created_at', $filters->interval).' AS bucket')
            ->when($byChannel, fn (Builder $query) => $query->addSelect('so.channel')->groupBy('so.channel'))
            ->selectRaw(implode(', ', SaleSql::orderAggregates()))
            ->groupBy('bucket');
    }

    public function summaryByChannel(ReportFilters $filters): array
    {
        $rows = DB::query()
            ->fromSub(SaleSql::orders($filters->withChannel(null)), 'so')
            ->select('so.channel')
            ->selectRaw(implode(', ', SaleSql::orderAggregates()))
            ->groupBy('so.channel')
            ->get()
            ->keyBy('channel');

        $summaries = [];
        foreach (OrderChannel::cases() as $channel) {
            $summaries[$channel->value] = self::cast((array) ($rows->get($channel->value) ?? []));
        }

        return $summaries;
    }

    public function channelTable(ReportFilters $filters): Builder
    {
        $net = 'so.gross - so.discount - so.returns';
        [$b2c, $b2b] = [OrderChannel::B2C->value, OrderChannel::B2B->value];

        return DB::query()
            ->fromSub(SaleSql::orders($filters->withChannel(null)), 'so')
            ->selectRaw(SaleSql::bucket('so.created_at', $filters->interval).' AS bucket')
            ->selectRaw('COUNT(*) FILTER (WHERE so.channel = ?) AS b2c_orders', [$b2c])
            ->selectRaw("ROUND(COALESCE(SUM({$net}) FILTER (WHERE so.channel = ?), 0), 2) AS b2c_net", [$b2c])
            ->selectRaw('COUNT(*) FILTER (WHERE so.channel = ?) AS b2b_orders', [$b2b])
            ->selectRaw("ROUND(COALESCE(SUM({$net}) FILTER (WHERE so.channel = ?), 0), 2) AS b2b_net", [$b2b])
            ->selectRaw("ROUND(COALESCE(SUM({$net}), 0), 2) AS net")
            ->groupBy('bucket');
    }

    public function lineTotals(ReportFilters $filters): array
    {
        $row = DB::query()
            ->fromSub(SaleSql::lines($filters, $this->categoryFilter($filters)), 'l')
            ->selectRaw(implode(', ', SaleSql::lineAggregates()))
            ->first();

        return [
            'orders' => (int) $row->orders,
            'units' => (int) $row->units,
            'gross' => (string) $row->gross,
            'discounts' => (string) $row->discounts,
            'returns' => (string) $row->returns,
            'net' => (string) $row->net,
            'returned_units' => (int) $row->returned_units,
            'exchanged_units' => (int) $row->exchanged_units,
        ];
    }

    public function productTable(ReportFilters $filters, string $by, string $locale): Builder
    {
        $categories = $this->categoryFilter($filters);
        $lines = SaleSql::lines($filters, $categories);
        $aggregates = implode(', ', SaleSql::lineAggregates());

        return match ($by) {
            'category' => DB::query()
                ->fromSub($lines, 'l')
                ->leftJoin(SaleSql::table('items', 'products_categories').' as pc', 'pc.product_id', '=', 'l.item_id')
                ->when($categories !== null, fn (Builder $query) => $query->whereIn('pc.category_id', $categories))
                ->selectRaw("COALESCE(pc.category_id::text, '') AS row_key")
                ->selectRaw('('.$this->categoryName('pc.category_id', $locale).') AS name')
                ->selectRaw('NULL AS sku')
                ->selectRaw($aggregates)
                ->groupBy('pc.category_id'),
            'color', 'size' => DB::query()
                ->fromSub($lines, 'l')
                ->leftJoinLateral($this->option($by === 'size'), 'x')
                ->selectRaw(SaleSql::localized("x.opt->'value'", $locale).' AS name')
                ->selectRaw('NULL AS sku')
                ->selectRaw($aggregates)
                ->groupBy('name')
                ->selectRaw("COALESCE(MAX(".SaleSql::localized("x.opt->'value'", $locale)."), '') AS row_key"),
            // A set is in a category by its own product, not its pieces': it is listed whole.
            'bundle' => $this->bundleTable(SaleSql::lines($filters)->when($categories !== null, fn (Builder $query) => $query->whereExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from(SaleSql::table('items', 'products_categories').' as pcb')
                ->whereColumn('pcb.product_id', 'oi.bundle_product_id')
                ->whereIn('pcb.category_id', $categories ?: [0]))), $locale),
            default => DB::query()
                ->fromSub($lines, 'l')
                ->leftJoin(SaleSql::table('items', 'products').' as ip', 'ip.id', '=', 'l.item_id')
                ->selectRaw("COALESCE(l.item_id::text, 'sku:' || COALESCE(l.sku, '')) AS row_key")
                ->selectRaw('MAX('.SaleSql::localized('l.product_name', $locale).') AS name')
                ->selectRaw('MAX(COALESCE(ip.sku, l.sku)) AS sku')
                ->selectRaw($aggregates)
                ->groupBy('row_key'),
        };
    }

    public function slowSellersTable(ReportFilters $filters, string $locale): Builder
    {
        $products = SaleSql::table('items', 'products');
        $categories = $this->categoryFilter($filters);

        // Stock on hand per product: its sellable items (variants, or itself) with managed stock.
        $stock = DB::table($products.' as i')
            ->whereNull('i.deleted_at')
            ->whereNull('i.bundle_type')
            ->where('i.manage_stock', true)
            ->where('i.is_active', ProductActive::IS_ACTIVE->value)
            ->whereNotExists(fn (Builder $variants) => $variants->selectRaw('1')->from($products.' as v')->whereColumn('v.parent_id', 'i.id')->whereNull('v.deleted_at'))
            ->selectRaw('COALESCE(i.parent_id, i.id) AS pid')
            ->selectRaw('SUM(GREATEST(i.stock_quantity, 0)) AS stock')
            ->selectRaw('COUNT(*) FILTER (WHERE i.stock_quantity > 0) AS items_in_stock')
            ->groupBy('pid')
            ->havingRaw('SUM(GREATEST(i.stock_quantity, 0)) > 0');

        $sold = DB::query()
            ->fromSub(SaleSql::lines($filters), 'l')
            ->groupBy('l.item_id')
            ->select('l.item_id')
            ->selectRaw('SUM(l.quantity) AS units')
            ->selectRaw('ROUND(SUM(l.gross - l.discount - l.returns), 2) AS net');

        $names = SaleSql::table('items', 'product_translations');

        return DB::query()
            ->fromSub($stock, 's')
            ->join($products.' as pp', 'pp.id', '=', 's.pid')
            ->leftJoinSub($sold, 'q', 'q.item_id', '=', 's.pid')
            ->whereNull('pp.deleted_at')
            ->whereNull('pp.bundle_type')
            ->where('pp.is_active', ProductActive::IS_ACTIVE->value)
            ->where('pp.status', ProductStatus::PUBLISHED->value)
            ->when($categories !== null, fn (Builder $query) => $query->whereExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from(SaleSql::table('items', 'products_categories').' as pc')
                ->whereColumn('pc.product_id', 's.pid')
                ->whereIn('pc.category_id', $categories ?: [0])))
            ->select('pp.id AS row_key', 'pp.sku', 's.stock', 's.items_in_stock')
            ->selectRaw("(SELECT pt.name FROM {$names} pt WHERE pt.product_id = pp.id ORDER BY (pt.locale = '{$locale}') DESC, (pt.locale = 'ar') DESC, pt.id LIMIT 1) AS name")
            ->selectRaw('COALESCE(q.units, 0) AS units')
            ->selectRaw('COALESCE(q.net, 0) AS net');
    }

    public function lastSold(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $productIds = array_map('intval', $productIds);

        // The products and their variants (an order line holds the variant), each to its product:
        // the lines are then found by their indexed product_id.
        $parentOf = DB::table(SaleSql::table('items', 'products'))
            ->whereIn('id', $productIds)
            ->orWhereIn('parent_id', $productIds)
            ->pluck('parent_id', 'id')
            ->mapWithKeys(fn ($parent, $id) => [(int) $id => in_array((int) $parent, $productIds, true) ? (int) $parent : (int) $id]);

        $last = [];

        foreach (array_chunk($parentOf->keys()->all(), 1000) as $chunk) {
            DB::table(SaleSql::table('items', 'order_items').' as oi')
                ->join(SaleSql::table('items', 'orders').' as o', 'o.id', '=', 'oi.order_id')
                ->whereNull('oi.deleted_at')
                ->whereNull('o.deleted_at')
                ->whereIn('o.status', SaleSql::statuses())
                ->whereIn('oi.product_id', $chunk)
                ->groupBy('oi.product_id')
                ->selectRaw('oi.product_id, MAX(o.created_at) AS last_sold')
                ->get()
                ->each(function (object $row) use ($parentOf, &$last): void {
                    $id = $parentOf[(int) $row->product_id];
                    $last[$id] = max($last[$id] ?? '', (string) $row->last_sold);
                });
        }

        return $last;
    }

    public function returnSummary(ReportFilters $filters): array
    {
        $totals = $this->lineTotals($filters);

        $requests = DB::table(SaleSql::table('items', 'return_requests').' as r')
            ->where('r.status', ReturnRequestStatus::COMPLETED->value)
            ->whereIn('r.id', DB::table(SaleSql::table('items', 'return_request_lines').' as rl')
                ->joinSub(SaleSql::lines($filters, $this->categoryFilter($filters)), 'l', 'l.line_id', '=', 'rl.order_item_id')
                ->select('rl.return_request_id'))
            ->count();

        return [
            'sold' => $totals['units'],
            'returned' => $totals['returned_units'],
            'exchanged' => $totals['exchanged_units'],
            'returns' => $totals['returns'],
            'requests' => $requests,
        ];
    }

    public function reasons(ReportFilters $filters): Collection
    {
        $lines = SaleSql::lines($filters, $this->categoryFilter($filters));
        $type = 'CASE WHEN l.status = '.OrderStatus::RETURNED->value." THEN '".ReturnRequestType::RETURN->value."' ELSE r.type END";

        $requested = DB::table(SaleSql::table('items', 'return_request_lines').' as rl')
            ->join(SaleSql::table('items', 'return_requests').' as r', 'r.id', '=', 'rl.return_request_id')
            ->joinSub($lines, 'l', 'l.line_id', '=', 'rl.order_item_id')
            ->where('r.status', ReturnRequestStatus::COMPLETED->value)
            ->select('r.reason')
            // A returned order counts every unit as returned (SaleSql::lines), pieces exchanged
            // before it came back included: their reason stays, their type follows.
            ->selectRaw("{$type} AS type")
            ->selectRaw('SUM(LEAST(rl.quantity, l.quantity)) AS units')
            ->groupByRaw("r.reason, {$type}")
            ->get();

        // A whole order sent back (refused at the door, or returned by its status) without a request.
        $whole = (int) DB::query()
            ->fromSub(SaleSql::lines($filters, $this->categoryFilter($filters)), 'l')
            ->where('l.status', OrderStatus::RETURNED->value)
            ->sum(DB::raw('GREATEST(l.quantity - l.requested_units, 0)'));

        return $whole > 0
            ? $requested->push((object) ['reason' => 'order_returned', 'type' => ReturnRequestType::RETURN->value, 'units' => $whole])
            : $requested;
    }

    public function returnTable(ReportFilters $filters, string $by, string $locale): Builder
    {
        return DB::query()
            ->fromSub($this->productTable($filters, $by, $locale), 't')
            ->select('t.*')
            ->selectRaw('COALESCE(ROUND(100.0 * t.returned_units / NULLIF(t.units, 0), 1), 0) AS rate');
    }

    public function categoryIds(int $root): array
    {
        $table = SaleSql::table('items', 'categories');

        return array_map(fn (object $row) => (int) $row->id, DB::select(
            "WITH RECURSIVE tree AS (SELECT id FROM {$table} WHERE id = ? UNION SELECT c.id FROM {$table} c JOIN tree t ON c.parent_id = t.id) SELECT id FROM tree",
            [$root],
        ));
    }

    /**
     * The category filter as the ids it covers (the category and its subcategories), or null.
     *
     * @return list<int>|null
     */
    private function categoryFilter(ReportFilters $filters): ?array
    {
        return $filters->categoryId !== null ? $this->categoryIds($filters->categoryId) : null;
    }

    /**
     * A category's name in $locale, else Arabic, else any.
     */
    private function categoryName(string $column, string $locale): string
    {
        $table = SaleSql::table('items', 'category_translations');

        return "SELECT ct.name FROM {$table} ct WHERE ct.category_id = {$column} ORDER BY (ct.locale = '{$locale}') DESC, (ct.locale = 'ar') DESC, ct.id LIMIT 1";
    }

    /**
     * The line's first option that is the colour (an attribute of type colour) or the size (the
     * attribute marked as size), matched by the attribute's name in the snapshot's languages.
     */
    private function option(bool $size): Builder
    {
        return DB::query()
            ->selectRaw('e AS opt')
            ->fromRaw("jsonb_array_elements(CASE WHEN jsonb_typeof(l.options) = 'array' THEN l.options ELSE '[]'::jsonb END) AS e")
            ->whereExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from(SaleSql::table('items', 'attribute_translations').' as at')
                ->join(SaleSql::table('items', 'attributes').' as a', 'a.id', '=', 'at.attribute_id')
                ->when($size, fn (Builder $query) => $query->where('a.is_size', true), fn (Builder $query) => $query->where('a.type', AttributeType::COLOR->value))
                ->whereRaw("e->'name'->>at.locale = at.name"))
            ->limit(1);
    }

    /**
     * Sets and bundles: one row per bundle, how many were sold (each sale's quantity, once) and what
     * their pieces add up to.
     */
    private function bundleTable(Builder $lines, string $locale): Builder
    {
        $groups = DB::query()
            ->fromSub($lines->whereNotNull('oi.bundle_product_id'), 'l')
            ->select('l.bundle_product_id')
            ->selectRaw("MAX(NULLIF(l.bundle->>'quantity', '')::numeric) AS bundles")
            ->selectRaw('MAX('.SaleSql::localized("l.bundle->'name'", $locale).') AS name')
            ->selectRaw("MAX(l.bundle->>'sku') AS sku")
            ->selectRaw('MIN(l.order_id) AS order_id')
            ->selectRaw('SUM(l.quantity) AS units, SUM(l.gross) AS gross, SUM(l.discount) AS discount, SUM(l.returns) AS returns, SUM(l.returned_units) AS returned_units, SUM(l.exchanged_units) AS exchanged_units')
            ->groupBy('l.bundle_product_id')
            ->groupByRaw('COALESCE(l.bundle_group, l.line_id::text)');

        return DB::query()
            ->fromSub($groups, 'g')
            ->selectRaw('g.bundle_product_id::text AS row_key')
            ->selectRaw('MAX(g.name) AS name, MAX(g.sku) AS sku')
            ->selectRaw('COALESCE(SUM(g.bundles), 0)::int AS bundles')
            ->selectRaw('COUNT(DISTINCT g.order_id) AS orders')
            ->selectRaw('COALESCE(SUM(g.units), 0) AS units')
            ->selectRaw('ROUND(COALESCE(SUM(g.gross), 0), 2) AS gross')
            ->selectRaw('ROUND(COALESCE(SUM(g.discount), 0), 2) AS discounts')
            ->selectRaw('ROUND(COALESCE(SUM(g.returns), 0), 2) AS returns')
            ->selectRaw('ROUND(COALESCE(SUM(g.gross - g.discount - g.returns), 0), 2) AS net')
            ->selectRaw('COALESCE(SUM(g.returned_units), 0) AS returned_units')
            ->selectRaw('COALESCE(SUM(g.exchanged_units), 0) AS exchanged_units')
            ->groupBy('g.bundle_product_id');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{orders: int, units: int, gross: string, discounts: string, delivery: string, returns: string, net: string, total: string, returned_units: int}
     */
    public static function cast(array $row): array
    {
        return [
            'orders' => (int) ($row['orders'] ?? 0),
            'units' => (int) ($row['units'] ?? 0),
            'gross' => (string) ($row['gross'] ?? '0.00'),
            'discounts' => (string) ($row['discounts'] ?? '0.00'),
            'delivery' => (string) ($row['delivery'] ?? '0.00'),
            'returns' => (string) ($row['returns'] ?? '0.00'),
            'net' => (string) ($row['net'] ?? '0.00'),
            'total' => (string) ($row['total'] ?? '0.00'),
            'returned_units' => (int) ($row['returned_units'] ?? 0),
        ];
    }
}
