<?php

namespace Modules\Reports\Repositories\Contracts;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Modules\Reports\Support\ReportFilters;

/**
 * Aggregates over sale orders and their lines (Reports\Support\SaleSql). Money comes back as
 * numeric strings rounded to 2 places; nothing is written.
 */
interface SalesReportRepositoryInterface
{
    /**
     * @return array{orders: int, units: int, gross: string, discounts: string, delivery: string, returns: string, net: string, total: string, returned_units: int}
     */
    public function summary(ReportFilters $filters): array;

    /**
     * Orders placed in the period that MALBO has not confirmed yet.
     *
     * @return array{orders: int, value: string}
     */
    public function pending(ReportFilters $filters): array;

    /**
     * The summary per bucket (`bucket`), and per `channel` with $byChannel.
     *
     * @return Collection<int, object>
     */
    public function timeline(ReportFilters $filters, bool $byChannel = false): Collection;

    /**
     * The summary columns per bucket, as a query a report table wraps.
     */
    public function timelineTable(ReportFilters $filters, bool $byChannel = false): Builder;

    /**
     * The summary of each channel, keyed b2c and b2b (a channel without sales has zeros). The
     * channel filter is ignored: both are always shown.
     *
     * @return array<string, array{orders: int, units: int, gross: string, discounts: string, delivery: string, returns: string, net: string, total: string, returned_units: int}>
     */
    public function summaryByChannel(ReportFilters $filters): array;

    /**
     * Per bucket: orders and net sales of each channel (b2c_orders, b2c_net, b2b_orders, b2b_net) and
     * the net of both.
     */
    public function channelTable(ReportFilters $filters): Builder;

    /**
     * What the sale lines of the filters (category included) add up to, delivery left out.
     *
     * @return array{orders: int, units: int, gross: string, discounts: string, returns: string, net: string, returned_units: int, exchanged_units: int}
     */
    public function lineTotals(ReportFilters $filters): array;

    /**
     * Sale lines grouped $by product (variants under their parent), category, color, size or bundle:
     * row_key, name, sku, orders, units, gross, discounts, returns, net, returned_units,
     * exchanged_units (+ bundles for bundles). Names in $locale (an app locale).
     */
    public function productTable(ReportFilters $filters, string $by, string $locale): Builder;

    /**
     * Published, active products (not sets or bundles) with managed stock on hand: row_key (the
     * product id), name, sku, stock (its items' positive stock), items_in_stock, and what sold in
     * the filters' period: units, net.
     */
    public function slowSellersTable(ReportFilters $filters, string $locale): Builder;

    /**
     * When each product (variants under their parent) last sold, all time: id => stored timestamp.
     *
     * @param  list<int>  $productIds
     * @return array<int, string>
     */
    public function lastSold(array $productIds): array;

    /**
     * Units sold, returned and exchanged on the filters' sale lines, the returns' value, and how many
     * completed requests they came through.
     *
     * @return array{sold: int, returned: int, exchanged: int, returns: string, requests: int}
     */
    public function returnSummary(ReportFilters $filters): array;

    /**
     * Units by reason and type of completed requests on the filters' sale lines, plus
     * `order_returned` for units of returned orders no request covers.
     *
     * @return Collection<int, object{reason: string, type: string, units: int|string}>
     */
    public function reasons(ReportFilters $filters): Collection;

    /**
     * productTable($by) with each row's return rate (`rate`, returned ÷ sold units, percent).
     */
    public function returnTable(ReportFilters $filters, string $by, string $locale): Builder;

    /**
     * A category and every category under it.
     *
     * @return list<int>
     */
    public function categoryIds(int $root): array;
}
