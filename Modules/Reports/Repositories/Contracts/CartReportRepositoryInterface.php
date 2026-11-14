<?php

namespace Modules\Reports\Repositories\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Modules\Reports\Support\ReportFilters;

/**
 * Carts started in the filters' period (store carts; wholesale carts for conversion), read only.
 */
interface CartReportRepositoryInterface
{
    /**
     * Store carts started in the period: started, converted (became an order), abandoned (no
     * order, has items, quiet for $abandonedAfterMinutes at $now), reachable (abandoned with the
     * buyer's consent to reminders), value (abandoned lines at $now's prices), reminded (a reminder
     * was sent), recovered (an order after a sent reminder, not cancelled or rejected) and
     * recovered_value (those orders' totals).
     *
     * @return array{started: int, converted: int, abandoned: int, reachable: int, value: string, reminded: int, recovered: int, recovered_value: string}
     */
    public function summary(ReportFilters $filters, int $abandonedAfterMinutes, CarbonInterface $now): array;

    /**
     * The summary's columns per bucket of the carts' start.
     */
    public function table(ReportFilters $filters, int $abandonedAfterMinutes, CarbonInterface $now): Builder;

    /**
     * Cart reminders sent in the period, by channel (mail, sms).
     *
     * @return Collection<int, object{channel: string, sent: int|string}>
     */
    public function reminders(ReportFilters $filters): Collection;

    /**
     * Carts started in the period and how many became an order, per channel (b2c: store carts,
     * b2b: wholesale carts).
     *
     * @return array<string, array{carts: int, converted: int}>
     */
    public function conversion(ReportFilters $filters): array;

    /**
     * Per bucket: b2c_carts, b2c_converted, b2b_carts, b2b_converted.
     */
    public function conversionTable(ReportFilters $filters): Builder;
}
