<?php

namespace Modules\Reports\Services;

use Modules\Core\Support\AdminTable;
use Modules\Items\Enums\OrderChannel;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportFormat;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportRow;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The store (B2C) against wholesale (B2B): each channel's figures, its share of the net sales and
 * of the orders, its change against the period before, and the two side by side per day or month.
 */
class ChannelReport
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
        $now = $this->sales->summaryByChannel($filters);
        $compared = $filters->period->previous();
        $before = $this->sales->summaryByChannel($filters->withPeriod($compared));
        $totalNet = bcadd($now['b2c']['net'], $now['b2b']['net'], 2);
        $totalOrders = $now['b2c']['orders'] + $now['b2b']['orders'];

        $channels = array_map(fn (OrderChannel $channel) => [
            'key' => $channel->value,
            'label' => $channel === OrderChannel::B2C ? __('Store (B2C)') : __('Wholesale (B2B)'),
            'summary' => SalesReport::summary($now[$channel->value]),
            'changes' => SalesReport::changes($now[$channel->value], $before[$channel->value]),
            'share' => [
                'net' => ReportFormat::rate($now[$channel->value]['net'], $totalNet),
                'orders' => ReportFormat::rate($now[$channel->value]['orders'], $totalOrders),
            ],
        ], OrderChannel::cases());

        $rows = $this->sales->channelTable($filters)->orderBy('bucket')->get()->keyBy('bucket');
        $keys = $filters->period->buckets($filters->interval);

        return [
            'filters' => $filters->toProps(),
            'branches' => $this->options->branches(),
            'channels' => $channels,
            'compare' => ['from' => $compared->from->format('Y-m-d'), 'to' => $compared->to->format('Y-m-d')],
            'chart' => [
                'keys' => $keys,
                'categories' => array_map(ReportFormat::bucket(...), $keys),
                'b2c' => array_map(fn (string $key) => (float) ($rows->get($key)->b2c_net ?? 0), $keys),
                'b2b' => array_map(fn (string $key) => (float) ($rows->get($key)->b2b_net ?? 0), $keys),
            ],
            'table' => $this->table($filters),
            'export_url' => $this->options->exportUrl('channels', $filters),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function table(ReportFilters $filters): array
    {
        return AdminTable::for(ReportRow::query()->fromSub($this->sales->channelTable($filters), 'report'))
            ->filters(...ReportFilters::allowed())
            ->sorts('bucket', 'b2c_orders', 'b2c_net', 'b2b_orders', 'b2b_net', 'net')
            ->defaultSort('-bucket')
            ->tieBreaker('bucket')
            ->rows(fn (ReportRow $row) => [
                'id' => $row->bucket,
                'bucket' => $row->bucket,
                'label' => ReportFormat::bucket($row->bucket),
                'b2c_orders' => (int) $row->b2c_orders,
                'b2c_net' => ReportMoney::format($row->b2c_net),
                'b2b_orders' => (int) $row->b2b_orders,
                'b2b_net' => ReportMoney::format($row->b2b_net),
                'net' => ReportMoney::format($row->net),
                'b2b_share' => ReportFormat::rate($row->b2b_net, $row->net),
            ]);
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->sales->channelTable($filters)->orderBy('bucket')->cursor()
            ->map(fn (object $row) => [
                $row->bucket,
                (int) $row->b2c_orders,
                ReportMoney::plain($row->b2c_net),
                (int) $row->b2b_orders,
                ReportMoney::plain($row->b2b_net),
                ReportMoney::plain($row->net),
                number_format(ReportFormat::rate($row->b2b_net, $row->net), 1, '.', ''),
            ]);

        return CsvExport::stream(
            'channels-'.$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Period'), __('Store orders'), __('Store net sales'), __('Wholesale orders'), __('Wholesale net sales'), __('Net sales'), __('Wholesale share %')],
            $rows,
        );
    }
}
