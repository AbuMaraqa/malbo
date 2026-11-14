<?php

namespace Modules\Reports\Services;

use Modules\Core\Support\AdminTable;
use Modules\Items\Enums\OrderChannel;
use Modules\Reports\Repositories\Contracts\CartReportRepositoryInterface;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Support\CsvExport;
use Modules\Reports\Support\ReportFilters;
use Modules\Reports\Support\ReportFormat;
use Modules\Reports\Support\ReportMoney;
use Modules\Reports\Support\ReportRow;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Conversion and average order value per channel. No visits or sessions are tracked, so the
 * conversion is cart-to-order: carts started in the period that became an order (store carts for
 * B2C, wholesale carts for B2B). The average order value is (gross − discounts) ÷ orders, over the
 * orders MALBO confirmed.
 */
class ConversionReport
{
    public function __construct(
        private readonly CartReportRepositoryInterface $carts,
        private readonly SalesReportRepositoryInterface $sales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(ReportFilters $filters): array
    {
        $filters = $filters->withChannel(null);
        $conversion = $this->carts->conversion($filters);
        $sales = $this->sales->summaryByChannel($filters);
        $orders = $sales['b2c']['orders'] + $sales['b2b']['orders'];
        $ordered = bcadd(self::ordered($sales['b2c']), self::ordered($sales['b2b']), 2);

        $carts = $this->carts->conversionTable($filters)->orderBy('bucket')->get()->keyBy('bucket');
        $timeline = $this->sales->timeline($filters, byChannel: true)->groupBy('channel')->map(fn ($rows) => $rows->keyBy('bucket'));
        $keys = $filters->period->buckets($filters->interval);
        $aov = fn (string $channel) => array_map(function (string $key) use ($timeline, $channel): float {
            $row = $timeline->get($channel)?->get($key);

            return $row ? (float) ReportFormat::average(bcsub((string) $row->gross, (string) $row->discounts, 2), (int) $row->orders) : 0.0;
        }, $keys);
        $rate = fn (string $channel) => array_map(fn (string $key) => ReportFormat::rate((int) ($carts->get($key)->{$channel.'_converted'} ?? 0), (int) ($carts->get($key)->{$channel.'_carts'} ?? 0)), $keys);

        return [
            'filters' => $filters->toProps(),
            'channels' => array_map(fn (OrderChannel $channel) => [
                'key' => $channel->value,
                'label' => $channel === OrderChannel::B2C ? __('Store (B2C)') : __('Wholesale (B2B)'),
                'carts' => $conversion[$channel->value]['carts'],
                'converted' => $conversion[$channel->value]['converted'],
                'conversion_rate' => ReportFormat::rate($conversion[$channel->value]['converted'], $conversion[$channel->value]['carts']),
                'orders' => $sales[$channel->value]['orders'],
                'aov' => ReportMoney::format(ReportFormat::average(self::ordered($sales[$channel->value]), $sales[$channel->value]['orders'])),
            ], OrderChannel::cases()),
            'aov' => ReportMoney::format(ReportFormat::average($ordered, $orders)),
            'chart' => [
                'keys' => $keys,
                'categories' => array_map(ReportFormat::bucket(...), $keys),
                'b2c_rate' => $rate('b2c'),
                'b2b_rate' => $rate('b2b'),
                'b2c_aov' => $aov('b2c'),
                'b2b_aov' => $aov('b2b'),
            ],
            'table' => AdminTable::for(ReportRow::query()->fromSub($this->carts->conversionTable($filters), 'report'))
                ->filters(...ReportFilters::allowed())
                ->sorts('bucket', 'b2c_carts', 'b2c_converted', 'b2b_carts', 'b2b_converted')
                ->defaultSort('-bucket')
                ->tieBreaker('bucket')
                ->rows(fn (ReportRow $row) => [
                    'id' => $row->bucket,
                    'bucket' => $row->bucket,
                    'label' => ReportFormat::bucket($row->bucket),
                    'b2c_carts' => (int) $row->b2c_carts,
                    'b2c_converted' => (int) $row->b2c_converted,
                    'b2c_rate' => ReportFormat::rate((int) $row->b2c_converted, (int) $row->b2c_carts),
                    'b2b_carts' => (int) $row->b2b_carts,
                    'b2b_converted' => (int) $row->b2b_converted,
                    'b2b_rate' => ReportFormat::rate((int) $row->b2b_converted, (int) $row->b2b_carts),
                ]),
            'export_url' => route('reports.conversion.export', ['filter' => $filters->query()]),
        ];
    }

    public function export(ReportFilters $filters): StreamedResponse
    {
        $rows = $this->carts->conversionTable($filters)->orderBy('bucket')->cursor()
            ->map(fn (object $row) => [
                $row->bucket,
                (int) $row->b2c_carts,
                (int) $row->b2c_converted,
                number_format(ReportFormat::rate((int) $row->b2c_converted, (int) $row->b2c_carts), 1, '.', ''),
                (int) $row->b2b_carts,
                (int) $row->b2b_converted,
                number_format(ReportFormat::rate((int) $row->b2b_converted, (int) $row->b2b_carts), 1, '.', ''),
            ]);

        return CsvExport::stream(
            'conversion-'.$filters->period->from->format('Y-m-d').'-'.$filters->period->to->format('Y-m-d').'.csv',
            [__('Period'), __('Store carts'), __('Store carts ordered'), __('Store conversion %'), __('Wholesale carts'), __('Wholesale carts ordered'), __('Wholesale conversion %')],
            $rows,
        );
    }

    /**
     * Gross less discounts: what the average order value divides.
     *
     * @param  array<string, int|string>  $summary
     */
    private static function ordered(array $summary): string
    {
        return bcsub((string) $summary['gross'], (string) $summary['discounts'], 2);
    }
}
