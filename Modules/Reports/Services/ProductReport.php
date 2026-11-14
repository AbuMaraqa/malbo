<?php

namespace Modules\Reports\Services;

use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportRow;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales by product (variants under their product), category, colour, size, or set and bundle.
 * Bundle pieces count under their own product, colour and size; sets also have their own rows.
 */
class ProductReport
{
    public const GROUPINGS = ['product', 'category', 'color', 'size', 'bundle'];

    private const MONEY = ['gross', 'discounts', 'returns', 'net'];

    public function __construct(
        private readonly SalesReportRepositoryInterface $sales,
        private readonly ReportOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        $by = $filters->by(self::GROUPINGS, 'product');
        $totals = $this->sales->lineTotals($filters);
        $top = $this->sales->productTable($filters, $by, $filters->locale())->orderByDesc('net')->orderBy('row_key')->limit(10)->get();

        return [
            'filters' => $filters->toProps(),
            'by' => $by,
            'groupings' => self::groupings(),
            'branches' => $this->options->branches(),
            'categories' => $this->options->categories(),
            'totals' => [
                'orders' => $totals['orders'],
                'units' => $totals['units'],
                ...array_combine(self::MONEY, array_map(fn (string $key) => ReportMoney::format($totals[$key]), self::MONEY)),
            ],
            'chart' => [
                'labels' => $top->map(fn (object $row) => $this->name($row))->values()->all(),
                'net' => $top->map(fn (object $row) => (float) $row->net)->values()->all(),
                'units' => $top->map(fn (object $row) => (int) $row->units)->values()->all(),
            ],
            'table' => $this->table($filters, $by),
            'export_url' => $this->options->exportUrl('products', $filters),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function groupings(): array
    {
        return [
            ['value' => 'product', 'label' => __('Product')],
            ['value' => 'category', 'label' => __('Category')],
            ['value' => 'color', 'label' => __('Colour')],
            ['value' => 'size', 'label' => __('Size')],
            ['value' => 'bundle', 'label' => __('Sets and bundles')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function table(ReportFilters $filters, string $by): array
    {
        return AdminTable::for(ReportRow::query()->fromSub($this->sales->productTable($filters, $by, $filters->locale()), 'report'))
            ->filters(...self::tableFilters())
            ->sorts('name', 'orders', 'units', 'gross', 'returns', 'net', 'returned_units')
            ->defaultSort('-net')
            ->tieBreaker('row_key')
            ->rows(fn (ReportRow $row) => [
                'id' => $row->row_key === '' ? '-' : $row->row_key,
                'name' => $this->name($row),
                'sku' => $row->sku,
                'bundles' => $by === 'bundle' ? (int) $row->bundles : null,
                'orders' => (int) $row->orders,
                'units' => (int) $row->units,
                ...array_combine(self::MONEY, array_map(fn (string $key) => ReportMoney::format($row->{$key}), self::MONEY)),
                'returned_units' => (int) $row->returned_units,
            ]);
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $by = $filters->by(self::GROUPINGS, 'product');
        $rows = $filters->exportQuery(ReportRow::query()->fromSub($this->sales->productTable($filters, $by, $filters->locale()), 'report'), self::tableFilters())
            ->orderByDesc('net')
            ->orderBy('row_key')
            ->cursor()
            ->map(fn (object $row) => [
                $this->name($row),
                $row->sku,
                ...($by === 'bundle' ? [(int) $row->bundles] : []),
                (int) $row->orders,
                (int) $row->units,
                ...array_map(fn (string $key) => ReportMoney::plain($row->{$key}), self::MONEY),
                (int) $row->returned_units,
            ]);

        return CsvExport::stream(
            "products-{$by}-".$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Name'), __('SKU'), ...($by === 'bundle' ? [__('Sets sold')] : []), __('Orders'), __('Units'), __('Gross sales'), __('Discounts'), __('Returns'), __('Net sales'), __('Returned units')],
            $rows,
        );
    }

    /**
     * The table's filters: the report's own, a search over the name and SKU, a units range.
     *
     * @return list<\Spatie\QueryBuilder\AllowedFilter>
     */
    private static function tableFilters(): array
    {
        return [...ReportFilters::allowed(), ReportFilters::search(['name', 'sku']), ...AdminFilter::numberRange('units')];
    }

    private function name(object $row): string
    {
        return filled($row->name) ? (string) $row->name : __('Not set');
    }
}
