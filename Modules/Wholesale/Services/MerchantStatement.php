<?php

namespace Modules\Wholesale\Services;

use App\Support\Locales;
use Illuminate\Support\Carbon;
use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Support\OrderDocument;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\LedgerEntryType;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * A merchant's statement of account for a date range: the balance brought forward (every entry
 * before the range), each entry in the range with the balance after it, and the closing balance.
 * What the merchant reads in the portal and staff print in the admin. A payment's note is the
 * staff's and is left out; an adjustment's reason is shown.
 */
class MerchantStatement
{
    public function __construct(
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly MerchantCredit $credit,
    ) {}

    /**
     * The range the statement shows when none is asked for: this year so far.
     *
     * @return array{from: string, to: string}
     */
    public static function defaultRange(): array
    {
        return ['from' => Carbon::now()->startOfYear()->toDateString(), 'to' => Carbon::now()->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    public function for(Merchant $merchant, ?string $from, ?string $to): array
    {
        $money = fn (float $amount) => (new Price($amount))->format();
        $opening = $from !== null ? $this->ledger->balance((int) $merchant->id, before: $from) : 0.0;
        $balance = $opening;
        $debits = 0.0;
        $credits = 0.0;
        $rows = [];

        foreach ($this->ledger->between((int) $merchant->id, $from, $to) as $entry) {
            $amount = (float) $entry->amount;
            $balance = round($balance + $amount, 2);

            if ($amount > 0) {
                $debits += $amount;
            } else {
                $credits -= $amount;
            }

            $rows[] = [
                'id' => (int) $entry->id,
                'date' => $entry->entry_date->toDateString(),
                'type' => $entry->type->value,
                'type_label' => $entry->type->getLabel(),
                'description' => self::description($entry),
                'reference' => $entry->reference,
                ...($entry->type === LedgerEntryType::ADJUSTMENT && filled($entry->note) ? ['note' => $entry->note] : []),
                'amount' => $amount,
                'debit_label' => $amount > 0 ? $money($amount) : null,
                'credit_label' => $amount < 0 ? $money(-$amount) : null,
                'balance' => $balance,
                'balance_label' => $money($balance),
            ];
        }

        return [
            'shop' => (string) $merchant->shop_name,
            'owner' => (string) $merchant->owner_name,
            'phone' => (string) $merchant->phone,
            'from' => $from,
            'to' => $to,
            'opening' => round($opening, 2),
            'opening_label' => $money($opening),
            'rows' => $rows,
            'debits_label' => $money(round($debits, 2)),
            'credits_label' => $money(round($credits, 2)),
            'closing' => round($balance, 2),
            'closing_label' => $money($balance),
            'credit' => $this->credit->summary($merchant),
        ];
    }

    /**
     * The statement as an A4 PDF in $locale (Arabic and Hebrew right to left).
     */
    public function pdf(Merchant $merchant, ?string $from, ?string $to, string $locale): string
    {
        return OrderDocument::inLocale($locale, function () use ($merchant, $from, $to, $locale): string {
            $document = [
                ...$this->for($merchant, $from, $to),
                'locale' => $locale,
                'dir' => Locales::isRtl($locale) ? 'rtl' : 'ltr',
                'store' => (string) app(GeneralSettings::class)->site_name,
                'printed_at' => Carbon::now()->format('Y-m-d H:i'),
            ];

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'default_font' => 'dejavusans',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 12,
                'tempDir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'mpdf', // font metrics only, no store data
            ]);

            $pdf->SetDirectionality($document['dir']);
            $pdf->SetTitle(__('Statement of account').' — '.$document['shop']);
            $pdf->WriteHTML(view('wholesale::pdf.statement', ['doc' => $document])->render());

            return $pdf->Output('', Destination::STRING_RETURN);
        });
    }

    private static function description(LedgerEntry $entry): string
    {
        $number = $entry->order?->order_number ?? $entry->reference;

        return match ($entry->type) {
            LedgerEntryType::INVOICE => __('Order :number delivered', ['number' => $number]),
            LedgerEntryType::CREDIT_NOTE => __('Return on order :number', ['number' => $number]),
            LedgerEntryType::PAYMENT => trim(($entry->method?->getLabel() ?? __('Payment received')).' '.($entry->reference ?? '')),
            LedgerEntryType::ADJUSTMENT => __('Account adjustment'),
        };
    }
}
