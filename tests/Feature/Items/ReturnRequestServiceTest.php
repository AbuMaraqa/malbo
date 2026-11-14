<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Settings\StoreReturnSettings;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * Return and exchange requests: who may ask, how much, and what completing one does to the stock
 * and to the order.
 */
class ReturnRequestServiceTest extends DatabaseTestCase
{
    use MakesOrders;

    private ReturnRequestService $returns;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();
        $this->returns = app(ReturnRequestService::class);
    }

    public function test_a_customer_requests_a_return_of_a_delivered_order(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 3]]);

        $request = $this->returns->create($order, $this->data($order, [[0, 2]]), $this->customer);

        $this->assertSame(ReturnRequestStatus::REQUESTED, $request->status);
        $this->assertSame($this->customer->id, $request->user_id);
        $this->assertNull($request->created_by);
        $this->assertSame('200.00', $request->refund_amount);
        $this->assertSame([2], $request->lines->pluck('quantity')->all());
        $this->assertSame([ReturnRequestStatus::REQUESTED], $request->history->pluck('status')->all());
        // While it is open, no second request.
        $this->assertNotNull($this->returns->eligibility($order->fresh(), $this->customer));
    }

    public function test_requests_need_a_delivered_order_inside_the_window(): void
    {
        $product = $this->stocked(10);
        $pending = $this->order([[$product, 1]]);

        $this->assertRefused(fn () => $this->returns->create($pending, $this->data($pending, [[0, 1]]), $this->customer), 'order');

        $order = $this->delivered([[$product, 1]]);
        $this->setWindow(14);
        Carbon::setTestNow(now()->addDays(15));

        $this->assertNotNull($this->returns->eligibility($order, $this->customer));
        $this->assertRefused(fn () => $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer), 'order');

        Carbon::setTestNow();
    }

    public function test_staff_may_request_outside_the_window(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        Carbon::setTestNow(now()->addDays(60));

        $request = $this->returns->create($order, $this->data($order, [[0, 1]]), null, $this->staff->id);

        $this->assertSame($this->staff->id, $request->created_by);
        $this->assertSame($this->customer->id, $request->user_id);
        Carbon::setTestNow();
    }

    public function test_a_customer_cannot_ask_for_another_buyers_order(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        $other = \Modules\Core\Entities\User::forceCreate(['name' => 'Other', 'email' => 'other@orders.test', 'password' => 'secret-password']);

        $this->assertRefused(fn () => $this->returns->create($order, $this->data($order, [[0, 1]]), $other), 'order');
    }

    public function test_one_open_request_per_order(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 3]]);

        $first = $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer);
        $this->assertRefused(fn () => $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer), 'order');

        $this->returns->reject($first, 'لا', $this->staff->id);
        $this->assertSame(ReturnRequestStatus::REQUESTED, $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer)->status);
    }

    public function test_quantity_is_capped_by_earlier_requests(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 3]]);

        $this->assertRefused(fn () => $this->returns->create($order, $this->data($order, [[0, 4]]), $this->customer), 'lines.0.quantity');

        $first = $this->complete($this->returns->create($order, $this->data($order, [[0, 2]]), $this->customer));
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        $this->assertSame([$order->items->first()->id => 1], $this->returns->remaining($order->fresh()));
        $this->assertRefused(fn () => $this->returns->create($order->fresh(), $this->data($order, [[0, 2]]), $this->customer), 'lines.0.quantity');
        $this->assertSame(ReturnRequestStatus::COMPLETED, $first->status);
    }

    public function test_customer_exchange_must_be_a_sibling_variant(): void
    {
        [$shirt, $black] = $this->shirtWithVariant(10);
        $white = Product::factory()->variationOf($shirt)->create(['name' => 'قميص أبيض L', 'manage_stock' => true, 'stock_quantity' => 5, 'base_price' => 100]);
        $stranger = $this->stocked(5);
        $order = $this->delivered([[$black, 1]]);

        $this->assertRefused(fn () => $this->returns->create($order, $this->data($order, [[0, 1, $stranger->id]], 'exchange'), $this->customer), 'lines.0.exchange_product_id');

        $request = $this->returns->create($order, $this->data($order, [[0, 1, $white->id]], 'exchange'), $this->customer);
        $this->assertSame($white->id, $request->lines->first()->exchange_product_id);
        $this->assertSame('0.00', $request->refund_amount);
        $this->assertContains($white->id, array_column($this->returns->exchangeOptions($order->items->first()), 'value'));
    }

    public function test_staff_may_exchange_for_any_sellable_product(): void
    {
        $product = $this->stocked(10);
        $other = $this->stocked(5);
        $order = $this->delivered([[$product, 1]]);

        $request = $this->returns->create($order, $this->data($order, [[0, 1, $other->id]], 'exchange'), null, $this->staff->id);

        $this->assertSame($other->id, $request->lines->first()->exchange_product_id);
    }

    public function test_transitions_follow_the_rules_and_write_history(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        $request = $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer);

        $this->assertRefused(fn () => $this->returns->receive($request, null, $this->staff->id), 'status');
        $this->assertRefused(fn () => $this->returns->complete($request, $this->staff->id), 'status');

        $this->returns->approve($request, 'تمت الموافقة', $this->staff->id);
        $this->returns->receive($request->fresh(), null, $this->staff->id);

        $this->assertRefused(fn () => $this->returns->approve($request->fresh(), null, $this->staff->id), 'status');

        $history = $request->fresh()->history;
        $this->assertSame([ReturnRequestStatus::REQUESTED, ReturnRequestStatus::APPROVED, ReturnRequestStatus::RECEIVED], $history->pluck('status')->all());
        $this->assertSame('تمت الموافقة', $history[1]->note);
        $this->assertSame($this->staff->id, $history[1]->created_by);
    }

    public function test_completing_a_partial_return_restocks_and_keeps_the_order_delivered(): void
    {
        $a = $this->stocked(10);
        $b = $this->stocked(10);
        $order = $this->delivered([[$a, 2], [$b, 1]]);

        $request = $this->complete($this->returns->create($order, $this->data($order, [[0, 2]]), $this->customer));

        $this->assertSame(10, $a->fresh()->stock_quantity);
        $this->assertSame(9, $b->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame('200.00', $request->refund_amount);
        $this->assertSame(2, StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::RETURN)->sum('quantity'));
    }

    public function test_completing_a_full_return_restocks_once_and_returns_the_order(): void
    {
        $a = $this->stocked(10);
        $b = $this->stocked(10);
        $order = $this->delivered([[$a, 2], [$b, 1]]);

        // Two requests: the first part, then the rest. Nothing is ever given back twice.
        $this->complete($this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer));
        $this->complete($this->returns->create($order->fresh(), $this->data($order, [[0, 1], [1, 1]]), $this->customer));

        $this->assertSame(10, $a->fresh()->stock_quantity);
        $this->assertSame(10, $b->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertSame(3, (int) StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::RETURN)->sum('quantity'));
        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->statusHistory->last()->status);
    }

    public function test_a_manual_return_after_a_partial_one_gives_back_only_the_rest(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 3]]);
        $this->complete($this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer));
        $this->assertSame(8, $product->fresh()->stock_quantity);

        // Staff set the order to Returned by hand: the unit already back is not given back again.
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::RETURNED, $this->staff->id);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_a_partial_exchange_keeps_the_order_delivered_until_every_unit_is_back(): void
    {
        [$shirt, $black] = $this->shirtWithVariant(10);
        $white = Product::factory()->variationOf($shirt)->create(['name' => 'قميص أبيض L', 'manage_stock' => true, 'stock_quantity' => 5, 'base_price' => 100]);
        $order = $this->delivered([[$black, 2]]);

        $this->complete($this->returns->create($order, $this->data($order, [[0, 1, $white->id]], 'exchange'), $this->customer));

        $this->assertSame(9, $black->fresh()->stock_quantity);
        $this->assertSame(4, $white->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertNull($this->returns->eligibility($order->fresh(), $this->customer), 'the other unit can still be sent back');

        // The last unit is returned: everything is back, and the buyer got an exchange.
        $this->complete($this->returns->create($order->fresh(), $this->data($order, [[0, 1]]), $this->customer));

        $this->assertSame(10, $black->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::EXCHANGED, $order->fresh()->status);
    }

    public function test_refund_takes_the_discount_off_pro_rata(): void
    {
        $product = $this->stocked(10, ['base_price' => 200]);
        $order = $this->delivered([[$product, 1]]);
        $order->forceFill(['discount' => 50, 'total' => 150])->save(); // a 50 coupon

        $request = $this->returns->create($order->fresh(), $this->data($order, [[0, 1]]), $this->customer);
        $this->assertSame('150.00', $request->refund_amount);

        $this->assertSame('150.00', $this->complete($request)->refund_amount);
    }

    public function test_refunds_together_never_pass_what_the_goods_cost(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 3]]);
        $order->forceFill(['discount' => 10, 'delivery_fee' => 20, 'total' => 310])->save();

        $refunds = [];
        for ($i = 0; $i < 3; $i++) {
            $refunds[] = (float) $this->complete($this->returns->create($order->fresh(), $this->data($order, [[0, 1]]), $this->customer))->refund_amount;
        }

        // 96.67 a unit, rounded: the last one is held to what is left of 290 (total less delivery).
        $this->assertSame(290.0, round(array_sum($refunds), 2));
    }

    public function test_staff_reject_an_approved_or_received_request(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 2]]);

        $approved = $this->returns->approve($this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer), null, $this->staff->id);
        $this->assertSame(ReturnRequestStatus::REJECTED, $this->returns->reject($approved, 'لم تصل', $this->staff->id)->status);

        $received = $this->returns->create($order->fresh(), $this->data($order, [[0, 2]]), $this->customer);
        $this->returns->approve($received, null, $this->staff->id);
        $this->returns->receive($received->fresh(), null, $this->staff->id);
        $this->assertSame(ReturnRequestStatus::REJECTED, $this->returns->reject($received->fresh(), 'مستعملة', $this->staff->id)->status);

        $this->assertSame(10 - 2, $product->fresh()->stock_quantity, 'a rejection moves no stock');
        $this->assertNull($this->returns->eligibility($order->fresh(), $this->customer));
    }

    public function test_deleting_a_delivered_order_never_gives_stock_back(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 3]]);
        $this->complete($this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer));
        $this->assertSame(8, $product->fresh()->stock_quantity);

        app(OrderService::class)->delete($order->fresh(), $this->staff->id);

        $this->assertSame(8, $product->fresh()->stock_quantity, 'two units are with the buyer, one is already back');
    }

    public function test_deleting_an_exchanged_order_never_gives_stock_back(): void
    {
        [$shirt, $black] = $this->shirtWithVariant(10);
        $white = Product::factory()->variationOf($shirt)->create(['manage_stock' => true, 'stock_quantity' => 5, 'base_price' => 100]);
        $order = $this->delivered([[$black, 1]]);
        $this->complete($this->returns->create($order, $this->data($order, [[0, 1, $white->id]], 'exchange'), $this->customer));
        $this->assertSame(OrderStatus::EXCHANGED, $order->fresh()->status);

        app(OrderService::class)->delete($order->fresh(), $this->staff->id);

        $this->assertSame([10, 4], [$black->fresh()->stock_quantity, $white->fresh()->stock_quantity]);
    }

    public function test_the_lines_of_a_delivered_order_are_locked(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 2]]);
        $item = $order->items->first();

        $this->updateOrder($order, [['id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]])->assertSessionHasErrors('items');
        $this->updateOrder($order, [['id' => $item->id, 'product_id' => $product->id, 'quantity' => 2], ['product_id' => $product->id, 'quantity' => 1]])->assertSessionHasErrors('items');
        $this->assertSame(2, $item->fresh()->quantity);

        // Status, notes and payment status stay editable.
        $this->updateOrder($order, [['id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]], ['notes' => 'اتصل قبل', 'payment_status' => \Modules\Items\Enums\PaymentStatus::PAID->value])
            ->assertSessionHasNoErrors();
        $this->assertSame('اتصل قبل', $order->fresh()->notes);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('linesLocked', true)->etc());
    }

    public function test_status_messages_go_out_after_the_commit(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        $request = $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer);
        $this->returns->approve($request, null, $this->staff->id);
        $this->returns->receive($request->fresh(), null, $this->staff->id);

        $outside = \Illuminate\Support\Facades\DB::transactionLevel();
        $levels = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, function () use (&$levels) {
            $levels[] = \Illuminate\Support\Facades\DB::transactionLevel();

            throw new \RuntimeException('the message service is down');
        });

        $completed = $this->returns->complete($request->fresh(), $this->staff->id);

        $this->assertSame(ReturnRequestStatus::COMPLETED, $completed->status);
        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->assertNotEmpty($levels);
        $this->assertSame(array_fill(0, count($levels), $outside), $levels, 'a message was sent inside the transaction');
    }

    public function test_the_window_runs_to_the_end_of_its_last_day(): void
    {
        Carbon::setTestNow('2026-11-01 08:00:00');
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        $this->setWindow(14);

        Carbon::setTestNow('2026-11-15 20:00:00');
        $this->assertNull($this->returns->eligibility($order->fresh(), $this->customer));

        Carbon::setTestNow('2026-11-16 00:00:01');
        $this->assertNotNull($this->returns->eligibility($order->fresh(), $this->customer));
        Carbon::setTestNow();
    }

    public function test_without_a_delivered_row_the_window_starts_at_the_order_not_its_last_edit(): void
    {
        $order = Order::query()->create(['order_number' => 'OLD-1', 'sub_total' => 100, 'total' => 100, 'status' => OrderStatus::DELIVERED, 'user_id' => $this->customer->id]);
        $order->items()->create(['product_id' => $this->stocked(5)->id, 'quantity' => 1, 'price' => 100, 'total_price' => 100]);
        $order->forceFill(['created_at' => now()->subDays(30)])->save(); // touched today, placed a month ago
        $this->setWindow(14);

        $this->assertNotNull($this->returns->eligibility($order->fresh(['items']), $this->customer));
    }

    public function test_an_exchange_short_of_stock_is_refused_and_nothing_moves(): void
    {
        [$shirt, $black] = $this->shirtWithVariant(10);
        $white = Product::factory()->variationOf($shirt)->create(['name' => 'قميص أبيض L', 'manage_stock' => true, 'stock_quantity' => 5, 'base_price' => 100]);
        $order = $this->delivered([[$black, 1]]);
        $request = $this->returns->create($order, $this->data($order, [[0, 1, $white->id]], 'exchange'), $this->customer);
        $this->returns->approve($request, null, $this->staff->id);
        $this->returns->receive($request->fresh(), null, $this->staff->id);
        $white->update(['stock_quantity' => 0]);

        $this->assertRefused(fn () => $this->returns->complete($request->fresh(), $this->staff->id), 'status');

        $this->assertSame(ReturnRequestStatus::RECEIVED, $request->fresh()->status);
        $this->assertSame(9, $black->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    public function test_the_customer_is_notified_on_each_change(): void
    {
        Notification::fake();
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        Notification::fake(); // the order's own status messages are not this test's

        $request = $this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer);
        $this->returns->approve($request, null, $this->staff->id);

        Notification::assertSentToTimes($this->customer, GeneralNotification::class, 1);
    }

    public function test_refunded_flag_and_staff_note(): void
    {
        $product = $this->stocked(10);
        $order = $this->delivered([[$product, 1]]);
        $request = $this->complete($this->returns->create($order, $this->data($order, [[0, 1]]), $this->customer));

        $this->returns->update($request, ['refunded' => true, 'staff_note' => 'دفع نقدا'], $this->staff->id);

        $request->refresh();
        $this->assertTrue($request->refunded);
        $this->assertNotNull($request->refunded_at);
        $this->assertSame('دفع نقدا', $request->staff_note);
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function order(array $lines): Order
    {
        $this->storeOrder(array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines))->assertSessionHasNoErrors();

        return $this->lastOrder()->load('items');
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function delivered(array $lines): Order
    {
        $order = $this->order($lines);

        foreach ([OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED] as $status) {
            app(OrderService::class)->changeStatus($order, $status, $this->staff->id);
        }

        return $order->fresh(['items']);
    }

    /**
     * @param  list<array{0: int, 1: int, 2?: int}>  $lines  [order line index, quantity, exchange product]
     * @return array<string, mixed>
     */
    private function data(Order $order, array $lines, string $type = 'return'): array
    {
        $items = $order->items()->orderBy('id')->get();

        return [
            'type' => $type,
            'reason' => 'wrong_size',
            'customer_note' => 'المقاس صغير',
            'lines' => array_map(fn (array $line) => [
                'order_item_id' => $items[$line[0]]->id,
                'quantity' => $line[1],
                'exchange_product_id' => $line[2] ?? null,
            ], $lines),
        ];
    }

    private function complete(ReturnRequest $request): ReturnRequest
    {
        $this->returns->approve($request, null, $this->staff->id);
        $this->returns->receive($request->fresh(), null, $this->staff->id);

        return $this->returns->complete($request->fresh(), $this->staff->id);
    }

    private function setWindow(int $days): void
    {
        $settings = app(StoreReturnSettings::class);
        $settings->return_window_days = $days;
        $settings->save();
    }

    private function assertRefused(callable $action, string $key): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), 'Refused, but on: '.implode(', ', array_keys($e->errors())));

            return;
        }

        $this->fail("Expected a refusal on {$key}.");
    }
}
