<?php

namespace Modules\Items\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\OrderStatusHistory;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Events\ReturnRequestCompleted;
use Modules\Items\Exceptions\InsufficientStock;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\BundleStock;
use Modules\Items\Repositories\Contracts\ReturnRequestRepositoryInterface;
use Modules\Store\Services\CartService;
use Modules\Store\Settings\StoreReturnSettings;
use Throwable;

/**
 * The only writer of return and exchange requests.
 *
 * Who may ask: the buyer whose order it is, for a DELIVERED order, within the store's return
 * window (counted from the day it was delivered); staff, for any delivered order. An order has at
 * most one open request. A line sends back at most what earlier requests that were not rejected or cancelled
 * left of it. An exchange is one for one: a buyer picks another sellable variant of the same
 * product (or the same one, for a faulty piece); staff may pick any sellable product.
 *
 * Completing a request, in one transaction: its units go back into stock (RETURN movements on the
 * order); an exchange's products are sold from stock (a shortage refuses the completion and
 * nothing moves). Once every unit of every line is back (returned or exchanged), the order becomes
 * EXCHANGED when any of it was exchanged, else RETURNED; until then it stays DELIVERED and more
 * can be asked for. The stock is moved here and only here: the order's status changes with
 * `moveStock: false`, because OrderService would otherwise give every line back again on
 * RETURNED. The refund recorded is the units sent back at the price each line was sold for
 * (addons included), less their share of the order discount (line total / subtotal × discount);
 * the order's refunds together never pass its total less delivery. An exchange records none.
 * Paying it is outside the platform: staff tick `refunded`.
 *
 * The buyer gets a database notification on each status change, after the commit.
 */
class ReturnRequestService
{
    public function __construct(
        private readonly ReturnRequestRepositoryInterface $requests,
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly OrderService $orderService,
        private readonly InventoryService $inventory,
        private readonly CartService $cart,
    ) {}

    /**
     * Why no request can be made on this order now, or null when one can. With $customer, the
     * buyer's rules: their own order, inside the window.
     */
    public function eligibility(Order $order, ?User $customer = null): ?string
    {
        if ($customer !== null && (int) $order->user_id !== (int) $customer->id) {
            return __('This order is not linked to your account.');
        }

        if ($order->status !== OrderStatus::DELIVERED) {
            return __('Only a delivered order can be returned or exchanged.');
        }

        if ($customer !== null && Carbon::now()->gt($this->windowEnd($order))) {
            return __('The time to return or exchange this order is over.');
        }

        if ($this->requests->hasOpen($order->id)) {
            return __('This order already has an open return or exchange request.');
        }

        if (array_sum($this->remaining($order)) === 0) {
            return __('Every item of this order was already sent back.');
        }

        return null;
    }

    /**
     * The end of the last day a buyer may still ask: the window counted from when the order was
     * delivered (its first status row at DELIVERED or later). An order without such a row counts
     * from when it was placed: never from its last edit, which would move the window.
     */
    public function windowEnd(Order $order): Carbon
    {
        $order->loadMissing('statusHistory');
        $reached = [OrderStatus::DELIVERED, OrderStatus::RETURNED, OrderStatus::EXCHANGED];
        $delivered = $order->statusHistory->first(fn (OrderStatusHistory $row) => in_array($row->status, $reached, true))?->created_at ?? $order->created_at;

        return Carbon::parse($delivered)->addDays(max(0, app(StoreReturnSettings::class)->return_window_days))->endOfDay();
    }

    /**
     * The buyer withdraws a request nobody has handled yet.
     *
     * @throws ValidationException on `status`
     */
    public function cancel(ReturnRequest $request, User $buyer): ReturnRequest
    {
        if ((int) $request->user_id !== (int) $buyer->id || $request->status !== ReturnRequestStatus::REQUESTED) {
            throw ValidationException::withMessages(['status' => __('Only the buyer can cancel a request that is still waiting.')]);
        }

        return $this->move($request, ReturnRequestStatus::CANCELLED, null, null);
    }

    /**
     * Units of each order line that can still be sent back.
     *
     * @return array<int, int> order item id => quantity
     */
    public function remaining(Order $order): array
    {
        $taken = $this->requests->takenQuantities($order->id);

        return $order->items->mapWithKeys(fn (OrderItem $item) => [$item->id => max(0, (int) $item->quantity - ($taken[$item->id] ?? 0))])->all();
    }

    /**
     * What a buyer may take instead of this line: the sellable variants of the same product (the
     * same one included), or the product itself when it has no variants.
     *
     * @return list<array{value: int, label: string}>
     */
    public function exchangeOptions(OrderItem $item): array
    {
        $item->loadMissing('product');
        $product = $item->product;

        if ($product === null) {
            return [];
        }

        $ids = $product->parent_id
            ? $this->products->siblingVariants((int) $product->parent_id)->modelKeys()
            : [$product->id];

        return $this->products->forCart(array_map('intval', $ids))
            ->filter(fn (Product $candidate) => $this->givingProblem($item, $candidate, 1) === null)
            ->sortBy('id')
            ->map(fn (Product $candidate) => ['value' => $candidate->id, 'label' => self::productLabel($candidate)])
            ->values()
            ->all();
    }

    /**
     * @param  array{type: string, reason: string, customer_note?: ?string, staff_note?: ?string, lines: list<array{order_item_id: int, quantity: int, exchange_product_id?: ?int}>}  $data
     * @param  User|null  $customer  the buyer asking from their account; null when staff ask
     *
     * @throws ValidationException on `order`, `lines`, `lines.N.*`
     */
    public function create(Order $order, array $data, ?User $customer = null, ?int $staffId = null): ReturnRequest
    {
        $type = ReturnRequestType::from((string) $data['type']);
        $reason = ReturnReason::from((string) $data['reason']);

        $request = DB::transaction(function () use ($order, $data, $customer, $staffId, $type, $reason): ReturnRequest {
            // Locked: two requests on one order are checked one after the other.
            $locked = $this->orders->lockForUpdate($order->id);
            $locked->load('statusHistory');

            if ($why = $this->eligibility($locked, $customer)) {
                throw ValidationException::withMessages(['order' => $why]);
            }

            $remaining = $this->remaining($locked);
            $items = $locked->items->keyBy('id');
            $lines = [];
            $refund = 0.0;

            foreach (array_values($data['lines'] ?? []) as $index => $line) {
                $item = $items->get((int) ($line['order_item_id'] ?? 0));

                if ($item === null) {
                    throw ValidationException::withMessages(["lines.{$index}.order_item_id" => __('This item is not on the order.')]);
                }

                $quantity = (int) ($line['quantity'] ?? 0);
                $left = $remaining[$item->id] ?? 0;

                if ($quantity < 1 || $quantity > $left) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => __('You can send back up to :count of this item.', ['count' => $left])]);
                }

                $remaining[$item->id] = $left - $quantity;
                $exchangeId = null;

                if ($type === ReturnRequestType::EXCHANGE) {
                    $exchangeId = (int) ($line['exchange_product_id'] ?? 0);

                    if ($problem = $this->exchangeProblem($item, $exchangeId, $quantity, staff: $customer === null)) {
                        throw ValidationException::withMessages(["lines.{$index}.exchange_product_id" => $problem]);
                    }
                }

                $lines[] = ['order_item_id' => $item->id, 'quantity' => $quantity, 'exchange_product_id' => $exchangeId];
                $refund += self::unitRefund($locked, $item) * $quantity;
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => __('Choose at least one item to send back.')]);
            }

            /** @var ReturnRequest $request */
            $request = $this->requests->create([
                'order_id' => $locked->id,
                'user_id' => $locked->user_id,
                'type' => $type,
                'status' => ReturnRequestStatus::REQUESTED,
                'reason' => $reason,
                'customer_note' => filled($data['customer_note'] ?? null) ? trim((string) $data['customer_note']) : null,
                'staff_note' => filled($data['staff_note'] ?? null) ? trim((string) $data['staff_note']) : null,
                'refund_amount' => $type === ReturnRequestType::RETURN ? $this->cappedRefund($locked, $refund, null) : 0,
                'created_by' => $customer === null ? $staffId : null,
            ]);

            foreach ($lines as $line) {
                $request->lines()->create($line);
            }

            $request->history()->create(['status' => ReturnRequestStatus::REQUESTED, 'created_by' => $customer === null ? $staffId : null]);

            return $request;
        });

        return $request->fresh(['lines', 'history']) ?? $request;
    }

    /**
     * @throws ValidationException on `status`
     */
    public function approve(ReturnRequest $request, ?string $note, int $staffId): ReturnRequest
    {
        return $this->move($request, ReturnRequestStatus::APPROVED, $note, $staffId);
    }

    /**
     * @throws ValidationException on `status`
     */
    public function reject(ReturnRequest $request, ?string $note, int $staffId): ReturnRequest
    {
        return $this->move($request, ReturnRequestStatus::REJECTED, $note, $staffId);
    }

    /**
     * The items are back at MALBO.
     *
     * @throws ValidationException on `status`
     */
    public function receive(ReturnRequest $request, ?string $note, int $staffId): ReturnRequest
    {
        return $this->move($request, ReturnRequestStatus::RECEIVED, $note, $staffId);
    }

    /**
     * Stock and order follow, in one transaction (see the class comment).
     *
     * @throws ValidationException on `status`: not received yet, the order changed, or the exchange is short of stock
     */
    public function complete(ReturnRequest $request, int $staffId, ?string $note = null): ReturnRequest
    {
        return $this->move($request, ReturnRequestStatus::COMPLETED, $note, $staffId, function (ReturnRequest $locked) use ($staffId): array {
            $order = $this->orders->lockForUpdate($locked->order_id);

            if ($order->status !== OrderStatus::DELIVERED) {
                throw ValidationException::withMessages(['status' => __('The order is :status now: only a delivered order can be completed.', ['status' => $order->status->getLabel()])]);
            }

            $returned = [];
            $given = [];
            $refund = 0.0;

            foreach ($locked->lines as $line) {
                $item = $line->orderItem;
                $refund += $item ? self::unitRefund($order, $item) * $line->quantity : 0;

                if ($item?->product_id) {
                    $returned[$item->product_id] = ($returned[$item->product_id] ?? 0) + $line->quantity;
                }

                if ($locked->type === ReturnRequestType::EXCHANGE && $line->exchange_product_id) {
                    $given[$line->exchange_product_id] = ($given[$line->exchange_product_id] ?? 0) + $line->quantity;
                }
            }

            // Back first: an exchange for the same product (a faulty piece) takes the unit back out.
            $this->inventory->restock($returned, StockMovementType::RETURN, $order, $staffId);

            try {
                $this->inventory->sell($given, $order, $staffId);
            } catch (InsufficientStock $e) {
                throw ValidationException::withMessages(['status' => __('The exchange cannot be completed: :reason', ['reason' => collect($e->errors())->flatten()->first()])]);
            }

            $refundAmount = $locked->type === ReturnRequestType::RETURN ? $this->cappedRefund($order, $refund, $locked->id) : 0.0;

            // In this transaction, before the order may close below: a listener that credits the
            // refund (the wholesale ledger) sees it before the order's own RETURNED change.
            event(new ReturnRequestCompleted($locked, $order, $refundAmount, $staffId));

            // Only once every unit is back does the order close: exchanged when any of it was an
            // exchange (the buyer kept goods), else returned. Until then it stays delivered.
            if ($this->fullyReturned($order, $locked)) {
                $exchanged = $locked->type === ReturnRequestType::EXCHANGE || $this->requests->hasCompletedExchange($order->id);
                $this->orderService->changeStatus($order, $exchanged ? OrderStatus::EXCHANGED : OrderStatus::RETURNED, $staffId, moveStock: false);
            }

            return [
                'completed_at' => Carbon::now(),
                'refund_amount' => $refundAmount,
            ];
        });
    }

    /**
     * Staff's own fields: their note, and whether the refund was paid.
     *
     * @param  array{staff_note?: ?string, refunded?: bool}  $data
     *
     * @throws ValidationException on `refunded` before the request is completed
     */
    public function update(ReturnRequest $request, array $data, int $staffId): ReturnRequest
    {
        return DB::transaction(function () use ($request, $data): ReturnRequest {
            $locked = $this->requests->lockForUpdate($request->id);
            $refunded = (bool) ($data['refunded'] ?? $locked->refunded);

            if ($refunded && ! $locked->refunded && ($locked->status !== ReturnRequestStatus::COMPLETED || $locked->type !== ReturnRequestType::RETURN)) {
                throw ValidationException::withMessages(['refunded' => __('Only a completed return can be marked refunded.')]);
            }

            $locked->update([
                'staff_note' => array_key_exists('staff_note', $data) ? (filled($data['staff_note']) ? trim((string) $data['staff_note']) : null) : $locked->staff_note,
                'refunded' => $refunded,
                'refunded_at' => $refunded ? ($locked->refunded_at ?? Carbon::now()) : null,
            ]);

            return $locked;
        });
    }

    public function saveWindow(int $days): void
    {
        $settings = app(StoreReturnSettings::class);
        $settings->return_window_days = $days;
        $settings->save();
    }

    /**
     * A product as a buyer tells variants apart: its option values (Black · L), else its name.
     */
    public static function productLabel(Product $product): string
    {
        $values = $product->attributeValues->sortBy('attribute_id')->map(fn (AttributeValue $value) => (string) $value->name)->filter()->implode(' · ');
        $name = (string) (($product->parent ?? $product)->name ?? $product->sku ?? '#'.$product->id);

        return $values !== '' ? $name.' — '.$values : $name;
    }

    /**
     * @param  (callable(ReturnRequest): array<string, mixed>)|null  $work  runs on the locked request before the status changes; returns more columns to save
     */
    private function move(ReturnRequest $request, ReturnRequestStatus $to, ?string $note, ?int $staffId, ?callable $work = null): ReturnRequest
    {
        $saved = DB::transaction(function () use ($request, $to, $note, $staffId, $work): ReturnRequest {
            $locked = $this->requests->lockForUpdate($request->id);

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => __('A request that is :from cannot become :to.', [
                    'from' => $locked->status->getLabel(),
                    'to' => $to->getLabel(),
                ])]);
            }

            $more = $work ? $work($locked) : [];

            $locked->update(['status' => $to, ...$more]);
            $locked->history()->create(['status' => $to, 'note' => filled($note) ? trim((string) $note) : null, 'created_by' => $staffId]);

            DB::afterCommit(fn () => $this->notify($locked, $to));

            return $locked;
        });

        return $saved->fresh(['lines', 'history']) ?? $saved;
    }

    /**
     * Whether, with this request, every unit of every line of the order is back.
     */
    private function fullyReturned(Order $order, ReturnRequest $request): bool
    {
        $back = $this->requests->takenQuantities($order->id, completedOnly: true);

        foreach ($request->lines as $line) {
            $back[$line->order_item_id] = ($back[$line->order_item_id] ?? 0) + $line->quantity;
        }

        return $order->items->every(fn (OrderItem $item) => ($back[$item->id] ?? 0) >= (int) $item->quantity);
    }

    /**
     * Why this product cannot be given for the line, or null.
     */
    private function exchangeProblem(OrderItem $item, int $productId, int $quantity, bool $staff): ?string
    {
        if ($productId <= 0) {
            return __('Choose what to exchange this item for.');
        }

        $candidate = $this->products->forCart([$productId])->get($productId);

        if ($candidate === null) {
            return __('This product is no longer available.');
        }

        if (! $staff) {
            $item->loadMissing('product');
            $own = $item->product;
            $sameFamily = $own !== null && ($candidate->id === $own->id || ($own->parent_id !== null && (int) $candidate->parent_id === (int) $own->parent_id));

            if (! $sameFamily) {
                return __('An item can only be exchanged for another size or color of the same product.');
            }
        }

        return $this->givingProblem($item, $candidate, $quantity);
    }

    /**
     * Why this item cannot be given for the line now. A set's piece follows the set's rules
     * (BundleStock::pieceProblem): it need not be sold alone, so it need not be published; anything
     * else must be buyable on the storefront (CartService::problem).
     */
    private function givingProblem(OrderItem $item, Product $candidate, int $quantity): ?string
    {
        return $item->bundle_group !== null
            ? BundleStock::pieceProblem($candidate, $quantity)
            : $this->cart->problem($candidate, $quantity);
    }

    /**
     * What one unit of the line was sold for: its total (addons included) over its quantity.
     */
    public static function unitPrice(OrderItem $item): float
    {
        return (int) $item->quantity > 0 ? (float) $item->total_price / (int) $item->quantity : (float) $item->price;
    }

    /**
     * The order discount's share of one unit: the line's share of the subtotal times the discount.
     */
    public static function unitDiscount(Order $order, OrderItem $item): float
    {
        $subTotal = (float) $order->sub_total;

        return $subTotal > 0 ? self::unitPrice($item) / $subTotal * min((float) $order->discount, $subTotal) : 0.0;
    }

    /**
     * What one unit sent back gives back: its price, less its share of the order discount.
     */
    public static function unitRefund(Order $order, OrderItem $item): float
    {
        return max(0.0, self::unitPrice($item) - self::unitDiscount($order, $item));
    }

    /**
     * A request's refund, held so the order's refunds together never pass what its goods cost
     * (total less delivery): rounding a unit at a time could otherwise give back a cent more.
     */
    private function cappedRefund(Order $order, float $refund, ?int $requestId): float
    {
        $paidForGoods = max(0.0, (float) $order->total - (float) $order->delivery_fee);
        $left = max(0.0, $paidForGoods - $this->requests->refundedSoFar($order->id, $requestId));

        return round(min(round($refund, 2), $left), 2);
    }

    /**
     * Never fatal: the request has changed whether or not the message can go.
     */
    private function notify(ReturnRequest $request, ReturnRequestStatus $status): void
    {
        try {
            $request->loadMissing(['user', 'order' => fn ($order) => $order->withTrashed()]);
            $buyer = $request->user ?? $request->order?->user;

            $buyer?->notify(new GeneralNotification(
                title: __('Return request updated'),
                message: __('Your :type request for order #:number is now :status.', [
                    'type' => $request->type->getLabel(),
                    'number' => $request->order?->order_number,
                    'status' => $status->getLabel(),
                ]),
                url: route('store.account.orders.show', ['locale' => app()->getLocale(), 'number' => $request->order?->order_number]),
            ));
        } catch (Throwable $e) {
            Log::warning('Return request notification not sent: '.$e->getMessage(), ['return_request' => $request->id]);
        }
    }
}
