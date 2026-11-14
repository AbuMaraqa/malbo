<?php

namespace Modules\Wholesale\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\LedgerEntryType;
use Modules\Wholesale\Enums\LedgerPaymentMethod;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;

/**
 * The only writer of merchants' ledgers. Entries are never changed or deleted: a mistake is put
 * right with an adjustment.
 *
 * - An invoice when a B2B order is delivered: its total, once per order.
 * - A credit note when a return request on a B2B order is completed: its refund, once per request.
 * - A credit note when a delivered B2B order is returned: whatever its goods were not yet credited.
 *   Credits never include the delivery fee (staff credit a fee with an adjustment), never pass the
 *   invoice, and an order never invoiced is never credited.
 * - An adjustment when an invoiced order's total changes (fee, discount, lines): the difference,
 *   linked to the order (source `order_total`); credits count the invoice with these differences.
 * - Cash on delivery: once the order is delivered and marked paid, a cash payment of its total
 *   (source `cash_collected`, once per order); marked unpaid again, a reversing adjustment
 *   (`cash_reversed`); paid again, `cash_recollected`. Bank transfers are recorded by staff.
 * - Payments (a positive amount, recorded as −) and adjustments (signed, with a note) by staff.
 *
 * The platform's postings run inside the order's transaction (listeners of OrderStatusChanged,
 * OrderUpdated and ReturnRequestCompleted), on the locked order; every entry also locks the
 * merchant row.
 */
class MerchantLedger
{
    /** Where payment receipts are kept: the private disk. */
    public const DISK = 'local';

    public const ORDER_TOTAL = 'order_total';

    public const CASH_COLLECTED = 'cash_collected';

    public const CASH_REVERSED = 'cash_reversed';

    public const CASH_RECOLLECTED = 'cash_recollected';

    private const CASH_SOURCES = [self::CASH_COLLECTED, self::CASH_REVERSED, self::CASH_RECOLLECTED];

    /**
     * An invoiced order's total changed: the difference from what the ledger holds for it.
     */
    public function syncOrderTotal(Order $order, ?int $userId = null): void
    {
        $merchant = $this->merchantOf($order);

        if ($merchant === null) {
            return;
        }

        DB::transaction(function () use ($order, $merchant, $userId): void {
            $this->entries->lockMerchant((int) $merchant->id);
            $invoiced = $this->invoicedNet($order);

            if ($invoiced === null) {
                return;
            }

            $difference = round((float) $order->total - $invoiced, 2);

            if ($difference == 0) {
                return;
            }

            $money = fn (float $amount) => number_format($amount, 2, '.', ',');

            $this->entries->create([
                'merchant_id' => $merchant->id,
                'type' => LedgerEntryType::ADJUSTMENT,
                'amount' => $difference,
                'order_id' => $order->id,
                'reference' => $order->order_number,
                'note' => __('Order total changed: :old → :new', ['old' => $money($invoiced), 'new' => $money((float) $order->total)]),
                'entry_date' => Carbon::now()->toDateString(),
                'created_by' => $userId,
                'source' => self::ORDER_TOTAL,
            ]);
        });
    }

    /**
     * Cash on delivery follows the order's payment status once the goods reached the merchant.
     */
    public function syncCashPayment(Order $order, ?int $userId = null): void
    {
        $merchant = $this->merchantOf($order);

        if ($merchant === null || $order->payment_method !== PaymentMethod::CASH) {
            return;
        }

        DB::transaction(function () use ($order, $merchant, $userId): void {
            $this->entries->lockMerchant((int) $merchant->id);

            $paid = $order->payment_status === PaymentStatus::PAID;
            $delivered = in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::EXCHANGED], true);
            $recorded = $this->entries->sumBySource((int) $order->id, self::CASH_SOURCES) < 0;
            $total = round((float) $order->total, 2);
            $entry = fn (LedgerEntryType $type, float $amount, string $source, ?string $note) => $this->entries->create([
                'merchant_id' => $merchant->id,
                'type' => $type,
                'amount' => $amount,
                'order_id' => $order->id,
                'method' => $type === LedgerEntryType::PAYMENT ? LedgerPaymentMethod::CASH : null,
                'reference' => $order->order_number,
                'note' => $note,
                'entry_date' => Carbon::now()->toDateString(),
                'created_by' => $userId,
                'source' => $source,
            ]);

            if ($paid && $delivered && ! $recorded && $total > 0) {
                $this->entries->hasSource((int) $order->id, self::CASH_COLLECTED)
                    ? $entry(LedgerEntryType::ADJUSTMENT, -$total, self::CASH_RECOLLECTED, __('Cash on delivery collected again'))
                    : $entry(LedgerEntryType::PAYMENT, -$total, self::CASH_COLLECTED, null);
            } elseif (! $paid && $recorded) {
                $entry(LedgerEntryType::ADJUSTMENT, -$this->entries->sumBySource((int) $order->id, self::CASH_SOURCES), self::CASH_REVERSED, __('Cash on delivery marked unpaid again'));
            }
        });
    }

    public function __construct(
        private readonly LedgerEntryRepositoryInterface $entries,
        private readonly MerchantRepositoryInterface $merchants,
    ) {}

    /**
     * The delivered B2B order's invoice. Nothing for another channel, an order with no merchant, or
     * one already invoiced.
     */
    public function postInvoice(Order $order, ?int $userId = null): void
    {
        $merchant = $this->merchantOf($order);

        if ($merchant === null) {
            return;
        }

        DB::transaction(function () use ($order, $merchant, $userId): void {
            $this->entries->lockMerchant((int) $merchant->id);

            if ($this->entries->invoicedAmount((int) $order->id) !== null) {
                return;
            }

            $this->entries->create([
                'merchant_id' => $merchant->id,
                'type' => LedgerEntryType::INVOICE,
                'amount' => round((float) $order->total, 2),
                'order_id' => $order->id,
                'reference' => $order->order_number,
                'entry_date' => Carbon::now()->toDateString(),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * A completed return request's refund, as a credit note (capped by what the invoice has left).
     */
    public function postReturnCredit(ReturnRequest $request, Order $order, float $refund, ?int $userId = null): void
    {
        $merchant = $this->merchantOf($order);

        if ($merchant === null || $refund <= 0) {
            return;
        }

        DB::transaction(function () use ($request, $order, $merchant, $refund, $userId): void {
            $this->entries->lockMerchant((int) $merchant->id);

            if ($this->entries->hasCreditForReturn((int) $request->id)) {
                return;
            }

            $this->credit($merchant, $order, min($refund, $this->uncredited($order)), $userId, $request->id);
        });
    }

    /**
     * A delivered B2B order came back: what its invoice was not yet credited.
     */
    public function postReturnedOrderCredit(Order $order, ?int $userId = null): void
    {
        $merchant = $this->merchantOf($order);

        if ($merchant === null) {
            return;
        }

        DB::transaction(function () use ($order, $merchant, $userId): void {
            $this->entries->lockMerchant((int) $merchant->id);
            $this->credit($merchant, $order, $this->uncredited($order), $userId, null);
        });
    }

    /**
     * Money the merchant paid. The receipt (a PDF or an image) goes to the private disk.
     *
     * @param  array{amount: float|int|string, method: string, entry_date: string, reference?: ?string, note?: ?string}  $data
     *
     * @throws ValidationException on `amount` (not above 0) or `method`
     */
    public function recordPayment(Merchant $merchant, array $data, ?UploadedFile $receipt, int $userId): LedgerEntry
    {
        $amount = round((float) $data['amount'], 2);
        $method = LedgerPaymentMethod::tryFrom((string) ($data['method'] ?? ''));

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('Enter an amount above 0.')]);
        }

        if ($method === null) {
            throw ValidationException::withMessages(['method' => __('Choose how the merchant paid.')]);
        }

        $path = $receipt?->store('wholesale/receipts/'.$merchant->id, self::DISK);

        try {
            return $this->record($merchant, [
                'type' => LedgerEntryType::PAYMENT,
                'amount' => -$amount,
                'method' => $method,
                'reference' => self::text($data['reference'] ?? null, 100),
                'note' => self::text($data['note'] ?? null, 2000),
                'receipt_path' => $path ?: null,
                'receipt_name' => $receipt ? mb_substr($receipt->getClientOriginalName(), 0, 255) : null,
                'entry_date' => $data['entry_date'],
            ], $userId);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }

            throw $e;
        }
    }

    /**
     * A correction, + or −, always with the reason.
     *
     * @param  array{amount: float|int|string, entry_date: string, note: ?string, reference?: ?string}  $data
     *
     * @throws ValidationException on `amount` (0) or `note` (empty)
     */
    public function recordAdjustment(Merchant $merchant, array $data, int $userId): LedgerEntry
    {
        $amount = round((float) $data['amount'], 2);
        $note = self::text($data['note'] ?? null, 2000);

        if ($amount == 0) {
            throw ValidationException::withMessages(['amount' => __('Enter an amount other than 0.')]);
        }

        if ($note === null) {
            throw ValidationException::withMessages(['note' => __('Write the reason for this adjustment.')]);
        }

        return $this->record($merchant, [
            'type' => LedgerEntryType::ADJUSTMENT,
            'amount' => $amount,
            'reference' => self::text($data['reference'] ?? null, 100),
            'note' => $note,
            'entry_date' => $data['entry_date'],
        ], $userId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(Merchant $merchant, array $attributes, int $userId): LedgerEntry
    {
        /** @var LedgerEntry */
        return DB::transaction(function () use ($merchant, $attributes, $userId) {
            $this->entries->lockMerchant((int) $merchant->id);

            return $this->entries->create([...$attributes, 'merchant_id' => $merchant->id, 'created_by' => $userId]);
        });
    }

    private function credit(Merchant $merchant, Order $order, float $amount, ?int $userId, ?int $returnRequestId): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return;
        }

        $this->entries->create([
            'merchant_id' => $merchant->id,
            'type' => LedgerEntryType::CREDIT_NOTE,
            'amount' => -$amount,
            'order_id' => $order->id,
            'return_request_id' => $returnRequestId,
            'reference' => $order->order_number,
            'entry_date' => Carbon::now()->toDateString(),
            'created_by' => $userId,
        ]);
    }

    /**
     * What the order's goods have not been credited yet: its invoice with the later changes to its
     * total, less the credits so far and the delivery fee (never credited). 0 when never invoiced.
     */
    private function uncredited(Order $order): float
    {
        $invoiced = $this->invoicedNet($order);

        if ($invoiced === null) {
            return 0.0;
        }

        return max(0.0, round($invoiced - $this->entries->creditedAmount((int) $order->id) - (float) $order->delivery_fee, 2));
    }

    /**
     * The order's invoice with the differences posted since its total changed, or null when it was
     * never invoiced.
     */
    private function invoicedNet(Order $order): ?float
    {
        $invoiced = $this->entries->invoicedAmount((int) $order->id);

        return $invoiced === null ? null : round($invoiced + $this->entries->sumBySource((int) $order->id, [self::ORDER_TOTAL]), 2);
    }

    private function merchantOf(Order $order): ?Merchant
    {
        if ($order->channel !== OrderChannel::B2B || $order->user_id === null) {
            return null;
        }

        return $this->merchants->findForUser((int) $order->user_id);
    }

    private static function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
