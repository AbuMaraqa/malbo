<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportPeriod;
use Modules\Reports\Support\ReportRow;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Best sellers (the ten products that sold the most units in the period) and slow sellers:
 * published products with stock on hand, those that sold least first, with when they last sold.
 */
class SellerReport
{
    public function __construct(
        private readonly SalesReportRepositoryInterface $sales,
        private readonly ReportOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        $best = $this->sales->productTable($filters, 'product', $filters->locale())
            ->orderByDesc('units')->orderByDesc('net')->orderBy('row_key')->limit(10)->get();

        $table = AdminTable::for(ReportRow::query()->fromSub($this->sales->slowSellersTable($filters, $filters->locale()), 'report'))
            ->filters(...self::tableFilters())
            ->sorts('name', 'stock', 'units', 'net')
            ->defaultSort('units,-stock')
            ->tieBreaker('row_key')
            ->rows(fn (ReportRow $row) => $this->row($row));

        // When each listed product last sold: read for this page's rows only.
        $last = $this->lastSold(array_map(fn (array $row) => (int) $row['id'], $table['data']));
        $table['data'] = array_map(fn (array $row) => [...$row, 'last_sold' => $last[(int) $row['id']] ?? null], $table['data']);

        return [
            'filters' => $filters->toProps(),
            'categories' => $this->options->categories(),
            'best' => $best->map(fn (object $row) => [
                'name' => filled($row->name) ? (string) $row->name : __('Not set'),
                'sku' => $row->sku,
                'units' => (int) $row->units,
                'net' => ReportMoney::format($row->net),
            ])->values()->all(),
            'table' => $table,
            'export_url' => $this->options->exportUrl('sellers', $filters),
        ];
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $rows = $filters->exportQuery(ReportRow::query()->fromSub($this->sales->slowSellersTable($filters, $filters->locale()), 'report'), self::tableFilters())
            ->orderBy('units')->orderByDesc('stock')->orderBy('row_key')
            ->cursor()
            ->chunk(500)
            ->flatMap(function ($chunk) {
                $last = $this->lastSold($chunk->map(fn (object $row) => (int) $row->row_key)->all());

                return $chunk->map(fn (object $row) => [
                    (string) $row->name,
                    $row->sku,
                    (int) $row->stock,
                    (int) $row->items_in_stock,
                    (int) $row->units,
                    ReportMoney::plain($row->net),
                    $last[(int) $row->row_key] ?? '',
                ]);
            });

        return CsvExport::stream(
            'slow-sellers-'.$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Name'), __('SKU'), __('Stock on hand'), __('Items in stock'), __('Units sold'), __('Net sales'), __('Last sold')],
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ReportRow $row): array
    {
        return [
            'id' => (int) $row->row_key,
            'name' => (string) $row->name,
            'sku' => $row->sku,
            'stock' => (int) $row->stock,
            'items_in_stock' => (int) $row->items_in_stock,
            'units' => (int) $row->units,
            'net' => ReportMoney::format($row->net),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => the day it last sold, in the reports' timezone
     */
    private function lastSold(array $ids): array
    {
        return array_map(
            fn (string $at) => Carbon::parse($at, ReportPeriod::storedTimezone())->setTimezone(ReportPeriod::timezone())->format('Y-m-d'),
            $this->sales->lastSold($ids),
        );
    }

    /**
     * @return list<AllowedFilter>
     */
    private static function tableFilters(): array
    {
        return [...ReportFilters::allowed(), ReportFilters::search(['name', 'sku']), ...AdminFilter::numberRange('units')];
    }
}
