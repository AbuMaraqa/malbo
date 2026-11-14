<?php

namespace Modules\Reports\Services;

use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestType;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportFormat;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportRow;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Return rate: units returned ÷ units sold, for the orders of the period (a late return lowers the
 * period it was sold in). Exchanges have their own rate. Reasons come from completed requests; a
 * whole order sent back without one counts as "order returned".
 */
class ReturnReport
{
    public const GROUPINGS = ['product', 'category'];

    public function __construct(
        private readonly SalesReportRepositoryInterface $sales,
        private readonly ReportOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        $summary = $this->sales->returnSummary($filters);
        $by = $filters->by(self::GROUPINGS, 'product');

        return [
            'filters' => $filters->toProps(),
            'by' => $by,
            'groupings' => array_values(array_filter(ProductReport::groupings(), fn (array $option) => in_array($option['value'], self::GROUPINGS, true))),
            'categories' => $this->options->categories(),
            'summary' => [
                ...$summary,
                'returns' => ReportMoney::format($summary['returns']),
                'return_rate' => ReportFormat::rate($summary['returned'], $summary['sold']),
                'exchange_rate' => ReportFormat::rate($summary['exchanged'], $summary['sold']),
            ],
            'reasons' => $this->reasons($filters),
            'table' => AdminTable::for(ReportRow::query()->fromSub($this->sales->returnTable($filters, $by, $filters->locale()), 'report'))
                ->filters(...self::tableFilters())
                ->sorts('name', 'units', 'returned_units', 'exchanged_units', 'returns', 'rate')
                ->defaultSort('-rate')
                ->tieBreaker('row_key')
                ->rows(fn (ReportRow $row) => [
                    'id' => $row->row_key === '' ? '-' : $row->row_key,
                    'name' => filled($row->name) ? (string) $row->name : __('Not set'),
                    'sold' => (int) $row->units,
                    'returned' => (int) $row->returned_units,
                    'exchanged' => (int) $row->exchanged_units,
                    'returns' => ReportMoney::format($row->returns),
                    'rate' => (float) $row->rate,
                ]),
            'export_url' => $this->options->exportUrl('returns', $filters),
        ];
    }

    /**
     * @return list<array{key: string, reason: string, type: string, type_label: string, units: int}>
     */
    private function reasons(ReportFilters $filters): array
    {
        return $this->sales->reasons($filters)
            ->map(function (object $row) {
                $type = ReturnRequestType::tryFrom((string) $row->type) ?? ReturnRequestType::RETURN;
                $whole = $row->reason === 'order_returned';

                return [
                    'key' => $whole ? 'order_returned' : $type->value.':'.$row->reason,
                    'reason' => $whole ? __('Whole order returned') : (ReturnReason::tryFrom((string) $row->reason)?->getLabel() ?? (string) $row->reason),
                    'type' => $type->value,
                    'type_label' => $type->getLabel(),
                    'units' => (int) $row->units,
                ];
            })
            ->sortByDesc('units')
            ->values()
            ->all();
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $by = $filters->by(self::GROUPINGS, 'product');
        $rows = $filters->exportQuery(ReportRow::query()->fromSub($this->sales->returnTable($filters, $by, $filters->locale()), 'report'), self::tableFilters())
            ->orderByRaw('rate DESC NULLS LAST')
            ->orderBy('row_key')
            ->cursor()
            ->map(fn (object $row) => [
                filled($row->name) ? (string) $row->name : __('Not set'),
                (int) $row->units,
                (int) $row->returned_units,
                (int) $row->exchanged_units,
                ReportMoney::plain($row->returns),
                number_format((float) $row->rate, 1, '.', ''),
            ]);

        return CsvExport::stream(
            "returns-{$by}-".$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Name'), __('Units sold'), __('Returned units'), __('Exchanged units'), __('Returns'), __('Return rate %')],
            $rows,
        );
    }

    /**
     * The table's filters: the report's own, a name search, a range of units sold (so one unit
     * returned out of one sold does not lead the list).
     *
     * @return list<AllowedFilter>
     */
    private static function tableFilters(): array
    {
        return [...ReportFilters::allowed(), ReportFilters::search(['name']), ...AdminFilter::numberRange('sold', 'units')];
    }
}
