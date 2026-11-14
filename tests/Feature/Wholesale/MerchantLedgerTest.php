<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\LedgerEntryType;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Services\MerchantLedger;

/**
 * A merchant's account: an invoice for each delivered B2B order, credit notes for returns, payments
 * and adjustments by staff. + means the merchant owes more; − a payment or a credit.
 */
class MerchantLedgerTest extends WholesaleTestCase
{
    private Merchant $merchant;

    private User $staffUser;

    private Product $belt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant();
        $this->staffUser = $this->staff([]);
        $this->belt = $this->single(['stock_quantity' => 30]);
    }

    public function test_delivering_a_b2b_order_posts_its_invoice(): void
    {
        $order = $this->order(3);
        $this->moveTo($order, [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY]);
        $this->assertSame(0, LedgerEntry::query()->count());

        $this->moveTo($order, [OrderStatus::DELIVERED]);

        $entry = LedgerEntry::query()->sole();
        $this->assertSame(LedgerEntryType::INVOICE, $entry->type);
        $this->assertSame('60.00', $entry->amount);
        $this->assertSame($order->id, $entry->order_id);
        $this->assertSame($this->merchant->id, $entry->merchant_id);
        $this->assertSame(now()->toDateString(), $entry->entry_date->toDateString());
        $this->assertSame(60.0, $this->balance());
    }

    public function test_a_full_return_through_requests_never_credits_the_delivery_fee(): void
    {
        $order = $this->delivered(30, fee: 40); // 600 of goods + 40

        $this->returnRequest($order, 30);

        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(['-600.00'], $this->amounts(LedgerEntryType::CREDIT_NOTE));
        $this->assertSame(40.0, $this->balance());
    }

    public function test_a_returned_order_is_credited_its_goods_without_the_fee(): void
    {
        $order = $this->delivered(30, fee: 40);
        $this->returnRequest($order, 10); // -200

        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::RETURNED, $this->staffUser->id);

        $this->assertSame(['-200.00', '-400.00'], $this->amounts(LedgerEntryType::CREDIT_NOTE));
        $this->assertSame(40.0, $this->balance());
    }

    public function test_a_total_changed_after_delivery_is_posted(): void
    {
        $order = $this->delivered(3); // 60

        $this->staffEdit($order, ['delivery_fee' => 50]);

        $change = LedgerEntry::query()->where('type', LedgerEntryType::ADJUSTMENT)->sole();
        $this->assertSame('50.00', $change->amount);
        $this->assertSame($order->id, $change->order_id);
        $this->assertStringContainsString('60.00', $change->note);
        $this->assertStringContainsString('110.00', $change->note);
        $this->assertSame(110.0, $this->balance());

        $this->staffEdit($order, ['delivery_fee' => 50]); // no change: nothing more
        $this->assertSame(1, LedgerEntry::query()->where('type', LedgerEntryType::ADJUSTMENT)->count());

        // A full return afterwards credits the goods; the fee stays owed.
        $this->returnRequest($order, 3);
        $this->assertSame(['-60.00'], $this->amounts(LedgerEntryType::CREDIT_NOTE));
        $this->assertSame(50.0, $this->balance());
    }

    public function test_a_total_lowered_after_delivery_is_posted(): void
    {
        $order = $this->delivered(3, fee: 30); // 90

        $this->staffEdit($order, ['delivery_fee' => 0]);

        $this->assertSame(['-30.00'], $this->amounts(LedgerEntryType::ADJUSTMENT));
        $this->assertSame(60.0, $this->balance());
    }

    public function test_cash_on_delivery_paid_posts_a_payment_once(): void
    {
        $order = $this->delivered(3);

        $this->staffEdit($order, ['payment_status' => PaymentStatus::PAID->value]);
        $this->staffEdit($order, ['payment_status' => PaymentStatus::PAID->value, 'notes' => 'x']);

        $payment = LedgerEntry::query()->where('type', LedgerEntryType::PAYMENT)->sole();
        $this->assertSame('-60.00', $payment->amount);
        $this->assertSame('cash', $payment->method->value);
        $this->assertSame($order->order_number, $payment->reference);
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame(0.0, $this->balance());
    }

    public function test_paid_before_delivery_is_posted_on_delivery(): void
    {
        $order = $this->order(3);
        $this->moveTo($order, [OrderStatus::CONFIRMED, OrderStatus::PREPARING]);
        $this->staffEdit($order, ['payment_status' => PaymentStatus::PAID->value]);
        $this->assertSame(0, LedgerEntry::query()->count());

        $this->moveTo($order, [OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED]);

        $this->assertSame(['-60.00'], $this->amounts(LedgerEntryType::PAYMENT));
        $this->assertSame(0.0, $this->balance());
    }

    public function test_a_payment_marked_back_as_unpaid_is_reversed(): void
    {
        $order = $this->delivered(3);
        $this->staffEdit($order, ['payment_status' => PaymentStatus::PAID->value]);

        $this->staffEdit($order, ['payment_status' => PaymentStatus::UNPAID->value]);
        $this->assertSame(['60.00'], $this->amounts(LedgerEntryType::ADJUSTMENT));
        $this->assertSame(60.0, $this->balance());

        $this->staffEdit($order, ['payment_status' => PaymentStatus::PAID->value]);
        $this->assertSame(0.0, $this->balance());
        $this->assertSame(1, LedgerEntry::query()->where('type', LedgerEntryType::PAYMENT)->count());
    }

    public function test_bank_transfers_stay_manual(): void
    {
        $order = $this->delivered(3, method: PaymentMethod::BANK_TRANSFER);

        $this->staffEdit($order, ['payment_status' => PaymentStatus::PAID->value]);

        $this->assertSame(0, LedgerEntry::query()->where('type', LedgerEntryType::PAYMENT)->count());
        $this->assertSame(60.0, $this->balance());
    }

    public function test_an_invoice_is_posted_once_per_order(): void
    {
        $order = $this->delivered(3);

        app(MerchantLedger::class)->postInvoice($order->fresh());
        $this->assertSame(1, LedgerEntry::query()->count());

        $this->expectException(UniqueConstraintViolationException::class);
        LedgerEntry::query()->create(['merchant_id' => $this->merchant->id, 'type' => LedgerEntryType::INVOICE, 'amount' => 1, 'order_id' => $order->id, 'entry_date' => now()]);
    }

    public function test_b2c_orders_post_nothing(): void
    {
        $order = app(OrderService::class)->checkout([
            'user_id' => $this->merchant->user_id, 'customer_name' => 'x', 'contact_phone' => '0599', 'delivery_address' => 'x', 'payment_method' => 1,
        ], [['product_id' => $this->belt->id, 'quantity' => 1, 'price' => 40]]);

        $this->moveTo($order, [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED]);

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_a_completed_return_posts_a_credit_note(): void
    {
        $order = $this->delivered(3);

        $request = $this->returnRequest($order, 1);

        $credit = LedgerEntry::query()->where('type', LedgerEntryType::CREDIT_NOTE)->sole();
        $this->assertSame('-20.00', $credit->amount);
        $this->assertSame($request->id, $credit->return_request_id);
        $this->assertSame($order->id, $credit->order_id);
        $this->assertSame(40.0, $this->balance());
    }

    public function test_a_returned_order_is_credited_only_what_is_left(): void
    {
        $order = $this->delivered(3);
        $this->returnRequest($order, 1);

        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::RETURNED, $this->staffUser->id);

        $this->assertSame(['-20.00', '-40.00'], LedgerEntry::query()->where('type', LedgerEntryType::CREDIT_NOTE)->orderBy('id')->pluck('amount')->all());
        $this->assertSame(0.0, $this->balance());
    }

    public function test_a_full_return_request_credits_once(): void
    {
        $order = $this->delivered(3);

        $this->returnRequest($order, 3);

        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(['-60.00'], LedgerEntry::query()->where('type', LedgerEntryType::CREDIT_NOTE)->pluck('amount')->all());
        $this->assertSame(0.0, $this->balance());
    }

    public function test_an_order_returned_before_delivery_posts_nothing(): void
    {
        $order = $this->order(2);
        $this->moveTo($order, [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::RETURNED]);

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_payments_and_adjustments(): void
    {
        $this->delivered(3);
        $ledger = app(MerchantLedger::class);

        $payment = $ledger->recordPayment($this->merchant, ['amount' => 25, 'method' => 'bank_transfer', 'entry_date' => '2026-11-05', 'reference' => 'TR-1', 'note' => null], null, $this->staffUser->id);
        $this->assertSame('-25.00', $payment->amount);
        $this->assertSame(LedgerEntryType::PAYMENT, $payment->type);
        $this->assertSame($this->staffUser->id, $payment->created_by);

        $ledger->recordAdjustment($this->merchant, ['amount' => 5, 'entry_date' => '2026-11-06', 'note' => 'رسوم توصيل'], $this->staffUser->id);
        $ledger->recordAdjustment($this->merchant, ['amount' => -2.5, 'entry_date' => '2026-11-06', 'note' => 'خصم تسوية'], $this->staffUser->id);
        $this->assertSame(37.5, $this->balance());

        foreach ([
            fn () => $ledger->recordAdjustment($this->merchant, ['amount' => 5, 'entry_date' => '2026-11-06', 'note' => ' '], $this->staffUser->id),
            fn () => $ledger->recordAdjustment($this->merchant, ['amount' => 0, 'entry_date' => '2026-11-06', 'note' => 'x'], $this->staffUser->id),
            fn () => $ledger->recordPayment($this->merchant, ['amount' => -5, 'method' => 'cash', 'entry_date' => '2026-11-06'], null, $this->staffUser->id),
            fn () => $ledger->recordPayment($this->merchant, ['amount' => 5, 'method' => 'credit_card', 'entry_date' => '2026-11-06'], null, $this->staffUser->id),
        ] as $refused) {
            try {
                $refused();
                $this->fail('An invalid entry was posted.');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(37.5, $this->balance());
    }

    public function test_the_balance_and_running_balance(): void
    {
        $ledger = app(MerchantLedger::class);
        $ledger->recordAdjustment($this->merchant, ['amount' => 100, 'entry_date' => '2026-11-01', 'note' => 'رصيد افتتاحي'], $this->staffUser->id);
        $ledger->recordPayment($this->merchant, ['amount' => 30, 'method' => 'cash', 'entry_date' => '2026-11-03'], null, $this->staffUser->id);
        $ledger->recordPayment($this->merchant, ['amount' => 20, 'method' => 'cheque', 'entry_date' => '2026-11-02'], null, $this->staffUser->id);
        $ledger->recordAdjustment($this->merchant, ['amount' => 1, 'entry_date' => '2026-11-01', 'note' => 'x'], $this->staffUser->id);
        $other = $this->merchant();
        $ledger->recordAdjustment($other, ['amount' => 999, 'entry_date' => '2026-11-01', 'note' => 'x'], $this->staffUser->id);

        $repository = app(LedgerEntryRepositoryInterface::class);
        $this->assertSame(51.0, $repository->balance($this->merchant->id));
        $this->assertSame(101.0, $repository->balance($this->merchant->id, before: '2026-11-02'));

        // In date order, then as entered: the running balance after each.
        $rows = $repository->tableQuery($this->merchant->id)->reorder()->orderBy('entry_date')->orderBy('id')->get();
        $this->assertSame(['100.00', '101.00', '81.00', '51.00'], $rows->pluck('running_balance')->map(fn ($value) => number_format((float) $value, 2, '.', ''))->all());
    }

    private function order(int $quantity, float $fee = 0, PaymentMethod $method = PaymentMethod::CASH): Order
    {
        return app(OrderService::class)->checkout([
            'user_id' => $this->merchant->user_id, 'customer_name' => $this->merchant->shop_name, 'contact_phone' => $this->merchant->phone,
            'delivery_address' => 'x', 'payment_method' => $method->value, 'delivery_fee' => $fee, 'discount' => 0,
        ], [['product_id' => $this->belt->id, 'quantity' => $quantity, 'price' => 20]], OrderChannel::B2B);
    }

    private function delivered(int $quantity, float $fee = 0, PaymentMethod $method = PaymentMethod::CASH): Order
    {
        $order = $this->order($quantity, $fee, $method);
        $this->moveTo($order, [OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED]);

        return $order->fresh();
    }

    /**
     * @param  list<OrderStatus>  $statuses
     */
    private function moveTo(Order $order, array $statuses): void
    {
        foreach ($statuses as $status) {
            app(OrderService::class)->changeStatus($order->fresh(), $status, $this->staffUser->id);
        }
    }

    private function returnRequest(Order $order, int $quantity): ReturnRequest
    {
        $service = app(ReturnRequestService::class);
        $order = $order->fresh(['items']);
        $request = $service->create($order, ['type' => 'return', 'reason' => 'defective', 'lines' => [['order_item_id' => $order->items[0]->id, 'quantity' => $quantity]]], null, $this->staffUser->id);
        $service->approve($request, null, $this->staffUser->id);
        $service->receive($request, null, $this->staffUser->id);

        return $service->complete($request, $this->staffUser->id);
    }

    /**
     * Saves the order as the admin's form does, its lines unchanged, with these fields changed.
     *
     * @param  array<string, mixed>  $changes
     */
    private function staffEdit(Order $order, array $changes): Order
    {
        $order = $order->fresh('items');

        return app(OrderService::class)->update($order->id, [
            'status' => $order->status->value,
            'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $item->quantity])->all(),
            'delivery_type' => DeliveryType::DELIVERY->value,
            'delivery_fee' => $order->delivery_fee,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            ...$changes,
        ], $this->staffUser->id);
    }

    /**
     * @return list<string>
     */
    private function amounts(LedgerEntryType $type): array
    {
        return LedgerEntry::query()->where('type', $type)->orderBy('id')->pluck('amount')->all();
    }

    private function balance(): float
    {
        return app(LedgerEntryRepositoryInterface::class)->balance($this->merchant->id);
    }
}
