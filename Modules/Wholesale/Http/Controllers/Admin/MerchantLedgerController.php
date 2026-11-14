<?php

namespace Modules\Wholesale\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Enums\PaymentStatus;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Enums\LedgerEntryType;
use Modules\Wholesale\Enums\LedgerPaymentMethod;
use Modules\Wholesale\Http\Requests\Admin\LedgerAdjustmentRequest;
use Modules\Wholesale\Http\Requests\Admin\LedgerPaymentRequest;
use Modules\Wholesale\Http\Requests\StatementRangeRequest;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Services\MerchantCredit;
use Modules\Wholesale\Services\MerchantLedger;
use Modules\Wholesale\Services\MerchantStatement;
use Modules\Wholesale\Support\LedgerDateSort;
use Spatie\QueryBuilder\AllowedSort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A merchant's finance page: its ledger (newest first, with the balance after each entry), the
 * totals and its credit; staff record payments and adjustments, open receipts and print statements.
 */
class MerchantLedgerController extends Controller
{
    /** How long a receipt link works. */
    private const LINK_MINUTES = 10;

    public function __construct(
        private readonly MerchantRepositoryInterface $merchants,
        private readonly LedgerEntryRepositoryInterface $entries,
        private readonly MerchantLedger $ledger,
        private readonly MerchantCredit $credit,
        private readonly MerchantStatement $statement,
    ) {}

    public function index(int $merchant): Response
    {
        $model = $this->merchants->forEdit($merchant);
        $money = fn (float $amount) => (new Price($amount))->format();
        $totals = $this->entries->totalsByType((int) $model->id);
        $credit = $this->credit->status($model);

        return Inertia::render('Wholesale/Merchant/Ledger', [
            'merchant' => ['id' => $model->id, 'shop_name' => $model->shop_name],
            'table' => AdminTable::for($this->entries->tableQuery((int) $model->id))
                ->search(['reference', 'note'], null)
                ->filters(
                    AdminFilter::exact('type'),
                    AdminFilter::exact('method'),
                    ...AdminFilter::dateRange('entry_date'),
                )
                ->sorts(AllowedSort::custom('entry_date', new LedgerDateSort), 'amount')
                ->defaultSort('-entry_date')
                ->rows(fn (LedgerEntry $entry) => [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date->toDateString(),
                    'type' => $entry->type->value,
                    'type_label' => $entry->type->getLabel(),
                    'type_tone' => $entry->type->tone(),
                    'amount' => (float) $entry->amount,
                    'amount_label' => $money((float) $entry->amount),
                    'running_balance' => round((float) $entry->getAttribute('running_balance'), 2),
                    'running_label' => $money((float) $entry->getAttribute('running_balance')),
                    'method' => $entry->method?->getLabel(),
                    'reference' => $entry->reference,
                    'note' => $entry->note,
                    'order' => $entry->order ? ['number' => $entry->order->order_number, 'url' => route('orders.edit', $entry->order->id)] : null,
                    // An invoice whose order is not marked paid: cash not collected, or a transfer not matched.
                    'unpaid' => $entry->type === LedgerEntryType::INVOICE && $entry->order?->payment_status !== PaymentStatus::PAID,
                    'by' => $entry->createdBy?->name,
                    'receipt_url' => $entry->receipt_path ? URL::temporarySignedRoute('merchants.receipt', now()->addMinutes(self::LINK_MINUTES), ['merchant' => $model->id, 'entry' => $entry->id]) : null,
                ]),
            'totals' => [
                'balance' => $credit['balance'],
                'balance_label' => $money($credit['balance']),
                'invoiced' => $totals[LedgerEntryType::INVOICE->value] ?? 0,
                'invoiced_label' => $money($totals[LedgerEntryType::INVOICE->value] ?? 0),
                'paid' => -($totals[LedgerEntryType::PAYMENT->value] ?? 0),
                'paid_label' => $money(-($totals[LedgerEntryType::PAYMENT->value] ?? 0)),
                'credited' => -($totals[LedgerEntryType::CREDIT_NOTE->value] ?? 0),
                'credited_label' => $money(-($totals[LedgerEntryType::CREDIT_NOTE->value] ?? 0)),
                'adjusted' => $totals[LedgerEntryType::ADJUSTMENT->value] ?? 0,
                'adjusted_label' => $money($totals[LedgerEntryType::ADJUSTMENT->value] ?? 0),
                'unpaid_invoices' => $this->entries->unpaidInvoiceCount((int) $model->id),
            ],
            'credit' => [
                'enabled' => $credit['enabled'],
                'limit_label' => $money($credit['limit']),
                'open_label' => $money($credit['open']),
                'room_label' => $money($credit['room']),
            ],
            'types' => LedgerEntryType::options(),
            'methods' => LedgerPaymentMethod::options(),
            'today' => Carbon::now()->toDateString(),
            'statementRange' => MerchantStatement::defaultRange(),
        ]);
    }

    public function storePayment(LedgerPaymentRequest $request, int $merchant): RedirectResponse
    {
        $this->ledger->recordPayment($this->merchants->forEdit($merchant), $request->safe()->except('receipt'), $request->file('receipt'), (int) $request->user()->id);

        return back()->with('success', __('Payment recorded'));
    }

    public function storeAdjustment(LedgerAdjustmentRequest $request, int $merchant): RedirectResponse
    {
        $this->ledger->recordAdjustment($this->merchants->forEdit($merchant), $request->validated(), (int) $request->user()->id);

        return back()->with('success', __('Adjustment recorded'));
    }

    /**
     * A payment's receipt, through a link the ledger page signed a few minutes ago.
     */
    public function receipt(int $merchant, int $entry): StreamedResponse
    {
        $model = $this->entries->findForMerchant($merchant, $entry);
        $path = $model?->receipt_path;

        abort_if(blank($path) || ! Storage::disk(MerchantLedger::DISK)->exists($path), 404);

        return Storage::disk(MerchantLedger::DISK)->download($path, $model->receipt_name ?: basename($path));
    }

    /**
     * The statement of account as a PDF, in the admin's language, opened in the browser to print.
     */
    public function statement(StatementRangeRequest $request, int $merchant): HttpResponse
    {
        $range = $request->range();

        return response($this->statement->pdf($this->merchants->forEdit($merchant), $range['from'], $range['to'], app()->getLocale()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement-'.$merchant.'-'.$range['to'].'.pdf"',
        ]);
    }
}
