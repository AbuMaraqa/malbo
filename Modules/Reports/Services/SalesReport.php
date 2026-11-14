<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Collection;
use Modules\Core\Support\AdminTable;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportFormat;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportRow;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daily and monthly sales: the period's totals against the period before, a chart over every day
 * (or month) of it, a table of the days that sold, and its CSV.
 */
class SalesReport
{
    /** The table's money columns, in order. */
    public const MONEY = ['gross', 'discounts', 'delivery', 'returns', 'net', 'total'];

    public function __construct(
        private readonly SalesReportRepositoryInterface $sales,
        private readonly ReportOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        $summary = $this->sales->summary($filters);
        $compared = $filters->period->previous();
        $previous = $this->sales->summary($filters->withPeriod($compared));
        $pending = $this->sales->pending($filters);

        return [
            'filters' => $filters->toProps(),
            'branches' => $this->options->branches(),
            'summary' => self::summary($summary),
            'changes' => self::changes($summary, $previous),
            'compare' => ['from' => $compared->from->format('Y-m-d'), 'to' => $compared->to->format('Y-m-d')],
            'pending' => ['orders' => $pending['orders'], 'value' => ReportMoney::format($pending['value'])],
            'chart' => $this->chart($filters, $this->sales->timeline($filters)),
            'table' => $this->table($filters),
            'export_url' => $this->options->exportUrl('sales', $filters),
        ];
    }

    /**
     * A summary as the page shows it: counts, money labels, and the average order value
     * ((gross − discounts) ÷ orders).
     *
     * @param  array<string, int|string>  $summary
     * @return array<string, int|string>
     */
    public static function summary(array $summary): array
    {
        $shown = $summary;

        foreach (self::MONEY as $key) {
            $shown[$key] = ReportMoney::format($summary[$key]);
        }

        $shown['aov'] = ReportMoney::format(ReportFormat::average(bcsub((string) $summary['gross'], (string) $summary['discounts'], 2), (int) $summary['orders']));

        return $shown;
    }

    /**
     * Each figure's change against the period before, in percent (null: nothing before).
     *
     * @param  array<string, int|string>  $now
     * @param  array<string, int|string>  $before
     * @return array<string, ?float>
     */
    public static function changes(array $now, array $before): array
    {
        $changes = [];

        foreach (['orders', 'units', 'gross', 'discounts', 'returns', 'net', 'total'] as $key) {
            $changes[$key] = ReportFormat::change($now[$key], $before[$key]);
        }

        return $changes;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{keys: list<string>, categories: list<string>, net: list<float>, orders: list<int>}
     */
    private function chart(ReportFilters $filters, Collection $rows): array
    {
        $byBucket = $rows->keyBy('bucket');
        $keys = $filters->period->buckets($filters->interval);

        return [
            'keys' => $keys,
            'categories' => array_map(ReportFormat::bucket(...), $keys),
            'net' => array_map(fn (string $key) => (float) ($byBucket->get($key)->net ?? 0), $keys),
            'orders' => array_map(fn (string $key) => (int) ($byBucket->get($key)->orders ?? 0), $keys),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function table(ReportFilters $filters): array
    {
        return AdminTable::for(ReportRow::query()->fromSub($this->sales->timelineTable($filters), 'report'))
            ->filters(...ReportFilters::allowed())
            ->sorts('bucket', 'orders', 'units', 'gross', 'returns', 'net')
            ->defaultSort('-bucket')
            ->tieBreaker('bucket')
            ->rows(fn (ReportRow $row) => [
                'id' => $row->bucket,
                'bucket' => $row->bucket,
                'label' => ReportFormat::bucket($row->bucket),
                'orders' => (int) $row->orders,
                'units' => (int) $row->units,
                ...array_combine(self::MONEY, array_map(fn (string $key) => ReportMoney::format($row->{$key}), self::MONEY)),
            ]);
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->sales->timelineTable($filters)->orderBy('bucket')->cursor()
            ->map(fn (object $row) => [
                $row->bucket,
                (int) $row->orders,
                (int) $row->units,
                ...array_map(fn (string $key) => ReportMoney::plain($row->{$key}), self::MONEY),
            ]);

        return CsvExport::stream(
            'sales-'.$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Period'), __('Orders'), __('Units'), __('Gross sales'), __('Discounts'), __('Delivery fees'), __('Returns'), __('Net sales'), __('Total sales')],
            $rows,
        );
    }
}
