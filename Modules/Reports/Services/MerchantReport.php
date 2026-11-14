<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Reports\Repositories\Contracts\MerchantReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportPeriod;
use Modules\Reports\Support\ReportRow;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Wholesale merchants: their B2B sales in the period (orders, units, net sales, average order),
 * their last order, what they owe now (the ledger's balance), their tier and sales rep.
 */
class MerchantReport
{
    public function __construct(
        private readonly MerchantReportRepositoryInterface $merchants,
        private readonly MerchantRepositoryInterface $merchantProfiles,
        private readonly TierRepositoryInterface $tiers,
        private readonly ReportOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        // The figures above the table count what the table shows (its filters applied).
        $shown = fn () => $filters->exportQuery(ReportRow::query()->fromSub($this->merchants->table($filters, $filters->locale()), 'report'), self::tableFilters());
        $totals = $this->merchants->totals($shown());
        $top = $shown()->where('orders', '>', 0)->orderByDesc('net')->orderBy('row_key')->limit(10)->get();

        return [
            'filters' => $filters->toProps(),
            'statuses' => MerchantStatus::options(),
            'tiers' => $this->tiers->options(),
            'sales_reps' => $this->merchantProfiles->salesRepOptions(),
            'totals' => [...$totals, 'net' => ReportMoney::format($totals['net']), 'balance' => ReportMoney::format($totals['balance'])],
            'chart' => [
                'labels' => $top->pluck('shop_name')->map(fn ($name) => (string) $name)->values()->all(),
                'net' => $top->map(fn (object $row) => (float) $row->net)->values()->all(),
            ],
            'table' => AdminTable::for(ReportRow::query()->fromSub($this->merchants->table($filters, $filters->locale()), 'report'))
                ->filters(...self::tableFilters())
                ->sorts('shop_name', 'orders', 'units', 'net', self::nullsLast('aov'), self::nullsLast('last_order_at'), 'balance')
                ->defaultSort('-net')
                ->tieBreaker('row_key')
                ->rows(fn (ReportRow $row) => [
                    'id' => (int) $row->row_key,
                    'shop_name' => (string) $row->shop_name,
                    'status' => $row->status,
                    'status_label' => MerchantStatus::tryFrom((string) $row->status)?->getLabel(),
                    'status_tone' => MerchantStatus::tryFrom((string) $row->status)?->tone(),
                    'tier' => $row->tier,
                    'sales_rep' => $row->sales_rep,
                    'orders' => (int) $row->orders,
                    'units' => (int) $row->units,
                    'net' => ReportMoney::format($row->net),
                    'aov' => $row->aov === null ? null : ReportMoney::format($row->aov),
                    'last_order' => self::day($row->last_order_at),
                    'balance' => ReportMoney::format($row->balance),
                    'credit_limit' => $row->credit_limit === null ? null : ReportMoney::format($row->credit_limit),
                    'ledger_url' => route('merchants.ledger', (int) $row->row_key),
                ]),
            'export_url' => $this->options->exportUrl('merchants', $filters),
        ];
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $rows = $filters->exportQuery(ReportRow::query()->fromSub($this->merchants->table($filters, $filters->locale()), 'report'), self::tableFilters())
            ->orderByDesc('net')
            ->orderBy('row_key')
            ->cursor()
            ->map(fn (object $row) => [
                (string) $row->shop_name,
                MerchantStatus::tryFrom((string) $row->status)?->getLabel() ?? $row->status,
                $row->tier,
                $row->sales_rep,
                (int) $row->orders,
                (int) $row->units,
                ReportMoney::plain($row->net),
                $row->aov === null ? '' : ReportMoney::plain($row->aov),
                self::day($row->last_order_at) ?? '',
                ReportMoney::plain($row->balance),
                $row->credit_limit === null ? '' : ReportMoney::plain($row->credit_limit),
            ]);

        return CsvExport::stream(
            'merchants-'.$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Shop'), __('Status'), __('Tier'), __('Sales rep'), __('Orders'), __('Units'), __('Net sales'), __('Average order value'), __('Last order'), __('Balance due'), __('Credit limit')],
            $rows,
        );
    }

    /**
     * A sort whose empty values (no order yet) come last both ways.
     */
    private static function nullsLast(string $column): AllowedSort
    {
        return AllowedSort::callback($column, fn (Builder $query, bool $descending) => $query->orderByRaw($column.($descending ? ' DESC' : ' ASC').' NULLS LAST'));
    }

    private static function day(?string $at): ?string
    {
        return $at === null ? null : Carbon::parse($at, ReportPeriod::storedTimezone())->setTimezone(ReportPeriod::timezone())->format('Y-m-d');
    }

    /**
     * @return list<AllowedFilter>
     */
    private static function tableFilters(): array
    {
        return [
            ...ReportFilters::allowed(),
            ReportFilters::search(['shop_name']),
            AdminFilter::exact('status'),
            AdminFilter::exact('tier', 'tier_id'),
            AdminFilter::exact('sales_rep', 'sales_rep_id'),
        ];
    }
}
