<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\AdminTable;
use Modules\Reports\Repositories\Contracts\CartReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportFormat;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportRow;
use Modules\Store\Settings\MarketingSettings;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Abandoned store carts: of the carts started in the period, how many became an order, how many
 * were left (and could be reminded), what they hold at today's prices, the reminders sent and the
 * carts they brought back. Wholesale carts are left out: reminders are a store feature.
 */
class CartReport
{
    public function __construct(
        private readonly CartReportRepositoryInterface $carts,
        private readonly MarketingSettings $settings,
        private readonly ReportOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        $minutes = (int) $this->settings->abandoned_after_minutes;
        $now = Carbon::now();
        $summary = $this->carts->summary($filters, $minutes, $now);
        $rows = $this->carts->table($filters, $minutes, $now)->orderBy('bucket')->get()->keyBy('bucket');
        $keys = $filters->period->buckets($filters->interval);

        return [
            'filters' => $filters->toProps(),
            'abandoned_after_minutes' => $minutes,
            'summary' => [
                ...$summary,
                'value' => ReportMoney::format($summary['value']),
                'recovered_value' => ReportMoney::format($summary['recovered_value']),
                'conversion_rate' => ReportFormat::rate($summary['converted'], $summary['started']),
                'abandonment_rate' => ReportFormat::rate($summary['abandoned'], $summary['started']),
                'recovery_rate' => ReportFormat::rate($summary['recovered'], $summary['reminded']),
            ],
            'reminders' => $this->carts->reminders($filters)->map(fn (object $row) => [
                'channel' => (string) $row->channel,
                'label' => $row->channel === 'sms' ? __('SMS') : __('Email'),
                'sent' => (int) $row->sent,
            ])->values()->all(),
            'chart' => [
                'keys' => $keys,
                'categories' => array_map(ReportFormat::bucket(...), $keys),
                'started' => array_map(fn (string $key) => (int) ($rows->get($key)->started ?? 0), $keys),
                'abandoned' => array_map(fn (string $key) => (int) ($rows->get($key)->abandoned ?? 0), $keys),
                'recovered' => array_map(fn (string $key) => (int) ($rows->get($key)->recovered ?? 0), $keys),
            ],
            'table' => AdminTable::for(ReportRow::query()->fromSub($this->carts->table($filters, $minutes, $now), 'report'))
                ->filters(...ReportFilters::allowed())
                ->sorts('bucket', 'started', 'converted', 'abandoned', 'value', 'recovered')
                ->defaultSort('-bucket')
                ->tieBreaker('bucket')
                ->rows(fn (ReportRow $row) => [
                    'id' => $row->bucket,
                    'bucket' => $row->bucket,
                    'label' => ReportFormat::bucket($row->bucket),
                    'started' => (int) $row->started,
                    'converted' => (int) $row->converted,
                    'abandoned' => (int) $row->abandoned,
                    'reachable' => (int) $row->reachable,
                    'value' => ReportMoney::format($row->value),
                    'reminded' => (int) $row->reminded,
                    'recovered' => (int) $row->recovered,
                    'recovered_value' => ReportMoney::format($row->recovered_value),
                ]),
            'export_url' => $this->options->exportUrl('carts', $filters),
        ];
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->carts->table($filters, (int) $this->settings->abandoned_after_minutes, Carbon::now())->orderBy('bucket')->cursor()
            ->map(fn (object $row) => [
                $row->bucket,
                (int) $row->started,
                (int) $row->converted,
                (int) $row->abandoned,
                ReportMoney::plain($row->value),
                (int) $row->reminded,
                (int) $row->recovered,
                ReportMoney::plain($row->recovered_value),
            ]);

        return CsvExport::stream(
            'abandoned-carts-'.$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Period'), __('Carts started'), __('Became an order'), __('Abandoned'), __('Abandoned value'), __('Reminded'), __('Recovered'), __('Recovered value')],
            $rows,
        );
    }
}
