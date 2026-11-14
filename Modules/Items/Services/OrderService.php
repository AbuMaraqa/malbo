<?php

namespace Modules\Items\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Contracts\OnAccountPolicy;
use Modules\Items\Contracts\OrderLinePricer;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Events\OrderStatusChanged;
use Modules\Items\Events\OrderUpdated;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\BundleAllocation;
use Modules\Store\Services\CouponService;
use Modules\Store\Services\PricingService;
use Throwable;

/**
 * Writes orders. Money is worked out here: a new line takes the product's current price, a line
 * already on the order keeps the price it was sold at, and subtotal and total follow from them. The
 * delivery fee is what the staff member typed, as nothing in the store defines one, and is 0 for
 * an order that is not delivered. Nothing else about money is read from the form.
 */
class OrderService
{
    /** decimal(10, 2) */
    private const MONEY_LIMIT = 99999999.99;

    private const COLUMNS = [
        'user_id', 'branch_id', 'city_id', 'district_id', 'payment_method', 'payment_status', 'delivery_type',
        'notes', 'delivery_address', 'contact_phone', 'latitude', 'longitude',
        'customer_name', 'customer_email', 'internal_notes',
    ];

    /** What a line stores about its product: its own fields plus the snapshot. */
    private const LINE_FIELDS = ['product_id', 'price', 'quantity', 'total_price', 'sku', 'product_name', 'options'];

    /** The relations snapshot() reads. */
    public const SNAPSHOT_RELATIONS = ['translations', 'parent.translations', 'attributeValues.translations', 'attributeValues.attribute.translations'];

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly PricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
        private readonly OrderLinePricer $linePricer,
        private readonly OnAccountPolicy $onAccount,
        private readonly SalesCounter $sales,
    ) {}

    /**
     * What each status may be chosen from: the current one and where it can go.
     *
     * @return list<OrderStatus>
     */
    public static function reachableFrom(OrderStatus $status): array
    {
        return array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $case) => $status->canTransitionTo($case)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Order
    {
        $status = OrderStatus::from((int) $data['status']);
        $this->assertTransition(OrderStatus::PENDING, $status);

        return DB::transaction(function () use ($data, $userId, $status): Order {
            $lines = $this->pricedLines($data['items'], collect());
            $totals = $this->totals($lines, 0, $data);

            if ((int) ($data['payment_method'] ?? 0) === PaymentMethod::ON_ACCOUNT->value) {
                $this->onAccount->assertAllowed(null, $totals['total']);
            }

            /** @var Order $order */
            $order = $this->orders->query()->newModelInstance();
            $order->fill([
                ...Arr::only($data, self::COLUMNS),
                ...$totals,
                'discount' => 0,
                'status' => $status,
                'created_by' => $userId,
                'order_number' => (string) Str::uuid(), // replaced below: it is built from the id
            ]);
            $order->save();

            $order->order_number = Carbon::now()->format('ydmHi').$order->id;
            $order->save();
            $this->recordStatus($order, $status, $userId);

            foreach ($lines as $line) {
                $order->items()->create(Arr::only($line, self::LINE_FIELDS));
            }

            if (! $status->releasesStock()) {
                $this->inventory->sell(self::quantities($lines), $order, $userId);
            }

            $this->sales->move([], SalesCounter::counts($order, $status) ? self::quantities($lines) : []);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $orderId, array $data, ?int $userId = null): Order
    {
        /** @var array{0: Order, 1: OrderStatus, 2: OrderStatus} $result */
        $result = DB::transaction(function () use ($orderId, $data, $userId): array {
            $order = $this->orders->lockForUpdate($orderId);
            $old = $order->status;
            $new = OrderStatus::from((int) $data['status']);

            $this->assertTransition($old, $new);

            $kept = $order->items->keyBy('id');
            $this->assertLinesUnchanged($order, $data['items'], $kept);
            self::assertBundlesWhole($data['items'], $kept);
            $locked = self::reachedTheBuyer($old) || $this->orders->hasReturnRequests($order->id);
            $before = self::quantities($kept); // before the lines below change
            $lines = $this->pricedLines($data['items'], $kept, $order);
            // A coupon is worked out again on the new lines; any other discount stays as it was, and
            // so does every discount once the lines are locked (refunds count on it).
            $discount = filled($order->coupon_code) && ! $locked ? $this->couponDiscount((string) $order->coupon_code, $lines) : (float) $order->discount;
            $totals = $this->totals($lines, $discount, $data);

            // On account: only where a module allows it (a merchant's credit), checked before the
            // order is saved, on what its total grows by (the order itself is already counted).
            if ((int) ($data['payment_method'] ?? $order->payment_method?->value) === PaymentMethod::ON_ACCOUNT->value) {
                $increase = $totals['total'] - (float) $order->total;

                if ($order->payment_method !== PaymentMethod::ON_ACCOUNT || $increase > 0) {
                    $this->onAccount->assertAllowed($order, max(0.0, round($increase, 2)));
                }
            }

            $order->fill([...Arr::only($data, self::COLUMNS), ...$totals, 'status' => $new])->save();

            $incoming = array_filter(array_column($lines, 'id'));
            $kept->except($incoming)->each(fn (OrderItem $item) => $item->delete());

            foreach ($lines as $line) {
                $fields = Arr::only($line, self::LINE_FIELDS);
                $item = $kept->get($line['id'] ?? null);

                // A pre-ordered line that is lowered keeps at most its new quantity as a pre-order;
                // another product on the line is no pre-order of it.
                if ($item?->preorder_quantity !== null) {
                    $fields['preorder_quantity'] = (int) $item->product_id === (int) $line['product_id'] ? min((int) $item->preorder_quantity, (int) $line['quantity']) : null;
                }

                $item ? $item->update($fields) : $order->items()->create($fields);
            }

            $this->moveStock($order, $old, $new, $before, self::quantities($lines), $userId);
            $this->sales->move(SalesCounter::counts($order, $old) ? $before : [], SalesCounter::counts($order, $new) ? self::quantities($lines) : []);

            if ($old !== $new) {
                $this->recordStatus($order, $new, $userId);
                // In this transaction, on the locked order: a cancelled order cancels its pending shipment.
                event(new OrderStatusChanged($order, $old, $new, $userId));
                $this->notifyAfterCommit($order, $new);
            }

            // In this transaction, on the locked order: the wholesale ledger follows its total and
            // payment status.
            event(new OrderUpdated($order, $userId));

            return [$order, $old, $new];
        });

        return $result[0];
    }

    /**
     * An order a buyer places (the mobile API), already priced by the caller. A cash order waits for
     * MALBO to confirm it; any other starts as new. Takes the stock in the same transaction.
     *
     * @param  array<string, mixed>  $header  order columns (order_number, payment_method, branch_id, ...)
     * @param  list<array{product_id: int, quantity: int, price: float, total_price: float, notes: ?string, options_to_attach: array<int, array{quantity: int, price: float}>}>  $lines
     *
     * @throws ValidationException when the stock is short
     */
    public function place(array $header, array $lines, int $userId): Order
    {
        $status = (int) $header['payment_method'] === PaymentMethod::CASH->value
            ? OrderStatus::AWAITING_CONFIRMATION
            : OrderStatus::PENDING;

        return DB::transaction(function () use ($header, $lines, $userId, $status): Order {
            $products = $this->products->query()
                ->with(self::SNAPSHOT_RELATIONS)
                ->whereKey(array_column($lines, 'product_id'))
                ->get()
                ->keyBy('id');

            self::assertNoBundleRows($products);

            /** @var Order $order */
            $order = $this->orders->query()->create([
                ...$header,
                'user_id' => $userId,
                'status' => $status,
                'payment_status' => PaymentStatus::UNPAID,
                'channel' => OrderChannel::B2C,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $item = $order->items()->create([
                    ...Arr::only($line, ['product_id', 'quantity', 'price', 'total_price', 'notes']),
                    ...$this->snapshot($products->get($line['product_id'])),
                ]);

                if (! empty($line['options_to_attach'])) {
                    $item->addonOptions()->attach($line['options_to_attach']);
                }
            }

            $this->inventory->sell(self::quantities($lines), $order, $userId);
            $this->sales->move([], self::quantities($lines));
            $this->recordStatus($order, $status, $userId);

            return $order;
        });
    }

    /**
     * A storefront order, already priced by the caller (CheckoutService): a guest's (no user) or a
     * signed-in buyer's (`user_id`); never a creator (no staff member made it, and a creator's
     * orders go when the creator is deleted) and no branch. A cash order waits for MALBO to confirm
     * it; it is unpaid. Takes the stock; a shortage throws and nothing is saved. The caller's
     * transaction holds it.
     *
     * $channel B2B is the wholesale portal's order (an approved merchant's, priced at wholesale by
     * the caller): it always waits for MALBO to confirm it, whatever the payment terms. Its sale may
     * reserve incoming goods (InventoryService::sellIncludingIncoming); a line's `preorder_quantity`
     * is how many of its pieces were reserved so. Staff edits later sell from the stock only.
     *
     * @param  array<string, mixed>  $header  user_id, customer_name, customer_email, contact_phone, delivery_address, city_id, district_id, notes, payment_method, delivery_fee, discount, coupon_code
     * @param  list<array{product_id: int, quantity: int, price: float}>  $lines
     *
     * @throws ValidationException when the stock is short
     */
    public function checkout(array $header, array $lines, OrderChannel $channel = OrderChannel::B2C): Order
    {
        $method = PaymentMethod::from((int) $header['payment_method']);
        $status = $method === PaymentMethod::CASH || $channel === OrderChannel::B2B ? OrderStatus::AWAITING_CONFIRMATION : OrderStatus::PENDING;

        return DB::transaction(function () use ($header, $lines, $method, $status, $channel): Order {
            $componentIds = array_merge([], ...array_map(fn (array $line) => array_column($line['components'] ?? [], 'product_id'), $lines));
            $products = $this->products->query()
                ->with(self::SNAPSHOT_RELATIONS)
                ->whereKey([...array_column($lines, 'product_id'), ...$componentIds])
                ->get()
                ->keyBy('id');

            $rows = [];

            foreach ($lines as $line) {
                $product = $products->get((int) $line['product_id']);

                if (! empty($line['components'])) {
                    if ($channel === OrderChannel::B2B || ! $product?->isBundle()) {
                        throw ValidationException::withMessages(['items' => __('A set or bundle is sold on the storefront only.')]);
                    }

                    array_push($rows, ...$this->bundleLines($product, (int) $line['quantity'], (float) $line['price'], $line['components'], $products));

                    continue;
                }

                if ($product?->isBundle()) {
                    throw ValidationException::withMessages(['items' => __('A set or bundle is sold on the storefront only.')]);
                }

                $rows[] = [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                    'price' => round((float) $line['price'], 2),
                    'total_price' => round((float) $line['price'] * (int) $line['quantity'], 2),
                ];
            }

            $lines = $rows;

            $subTotal = round(array_sum(array_column($lines, 'total_price')), 2);
            $discount = min(round((float) ($header['discount'] ?? 0), 2), $subTotal);
            $fee = round((float) ($header['delivery_fee'] ?? 0), 2);

            /** @var Order $order */
            $order = $this->orders->query()->newModelInstance();
            $order->fill([
                ...Arr::only($header, ['customer_name', 'customer_email', 'contact_phone', 'delivery_address', 'city_id', 'district_id', 'notes', 'coupon_code']),
                'user_id' => filled($header['user_id'] ?? null) ? (int) $header['user_id'] : null,
                'branch_id' => null,
                'created_by' => null,
                'channel' => $channel,
                'status' => $status,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::UNPAID,
                'delivery_type' => DeliveryType::DELIVERY,
                'sub_total' => $subTotal,
                'discount' => $discount,
                'delivery_fee' => $fee,
                'total' => round($subTotal - $discount + $fee, 2),
                'order_number' => (string) Str::uuid(), // replaced below: it is built from the id
            ]);
            $order->save();

            $order->order_number = Carbon::now()->format('ydmHi').$order->id;
            $order->save();
            $this->recordStatus($order, $status, null);

            $items = [];

            foreach ($lines as $line) {
                // A set's piece keeps its own snapshot (product, colour, size) next to the set's.
                $items[] = $order->items()->create([...$line, ...$this->snapshot($products->get($line['product_id']))]);
            }

            if ($channel === OrderChannel::B2B) {
                // A merchant may reserve incoming goods: each line records the pieces the stock
                // could not cover (its pre-order).
                $preorders = $this->inventory->sellIncludingIncoming(self::quantities($lines), $order, null);

                foreach (array_reverse($items) as $item) {
                    $left = $preorders[(int) $item->product_id] ?? 0;

                    if ($left > 0) {
                        $taken = min($left, (int) $item->quantity);
                        $item->update(['preorder_quantity' => $taken]);
                        $preorders[(int) $item->product_id] = $left - $taken;
                    }
                }
            } else {
                $this->inventory->sell(self::quantities($lines), $order, null);
            }

            $this->sales->move([], SalesCounter::counts($order, $status) ? self::quantities($lines) : []);

            return $order;
        });
    }

    /**
     * Moves an order to another status (the mobile API, the admin's confirm, a completed return
     * request). Checked against the locked row; stock follows as in update(); the buyer is told
     * after the commit.
     *
     * $moveStock false leaves the stock alone: a completed return request has already given back
     * exactly the units it took (ReturnRequestService::complete), so the order reaching RETURNED
     * must not give its lines back a second time.
     *
     * @throws ValidationException
     */
    public function changeStatus(Order $order, OrderStatus $to, int $userId, bool $moveStock = true): Order
    {
        /** @var array{0: Order, 1: OrderStatus} $result */
        $result = DB::transaction(function () use ($order, $to, $userId, $moveStock): array {
            $locked = $this->orders->lockForUpdate($order->id);
            $old = $locked->status;

            $this->assertTransition($old, $to);

            $locked->update(['status' => $to]);

            $quantities = self::quantities($locked->items);

            if ($moveStock) {
                $this->moveStock($locked, $old, $to, $quantities, $quantities, $userId);
            }

            // Whatever moved the stock (a completed return request did it already), the sale counts as the status says.
            $this->sales->move(SalesCounter::counts($locked, $old) ? $quantities : [], SalesCounter::counts($locked, $to) ? $quantities : []);

            if ($old !== $to) {
                $this->recordStatus($locked, $to, $userId);
                // In this transaction, on the locked order: a cancelled order cancels its pending shipment.
                event(new OrderStatusChanged($locked, $old, $to, $userId));
                $this->notifyAfterCommit($locked, $to);
            }

            return [$locked, $old];
        });

        return $result[0];
    }

    /**
     * Gives one guest order to the account that proved it is theirs (its number and the phone it
     * was placed with: AccountOrders::claim). Only an order with no account yet; on the locked row,
     * so two accounts claiming at once cannot both get it.
     *
     * @return bool whether the order is now the account's
     */
    public function claimGuestOrder(int $orderId, int $userId): bool
    {
        return DB::transaction(function () use ($orderId, $userId): bool {
            $order = $this->orders->lockForUpdate($orderId);

            if ($order->user_id !== null) {
                return (int) $order->user_id === $userId;
            }

            $order->update(['user_id' => $userId]);

            return true;
        });
    }

    /**
     * Soft: an order is a financial record, and a permanent delete would take its lines with it.
     */
    public function delete(Order $order, ?int $userId = null): void
    {
        DB::transaction(fn () => $this->sales->move($this->deleteLocked($order->id, $userId), []));
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids, ?int $userId = null): void
    {
        DB::transaction(function () use ($ids, $userId): void {
            $sold = [];

            foreach ($this->orders->query()->whereKey($ids)->orderBy('id')->pluck('id') as $id) {
                foreach ($this->deleteLocked((int) $id, $userId) as $productId => $quantity) {
                    $sold[$productId] = ($sold[$productId] ?? 0) + $quantity;
                }
            }

            // Once, after every order's stock: products, then their parents' counts, as a checkout locks them.
            $this->sales->move($sold, []);
        });
    }

    /**
     * A deleted order that still holds stock gives it back, as a cancellation would. Once delivered
     * (or exchanged, or returned) it never does: the goods are with the buyer, or are back already.
     */
    /**
     * @return array<int, int> the units the order counted as sold (SalesCounter), for the caller to take back
     */
    private function deleteLocked(int $orderId, ?int $userId): array
    {
        $order = $this->orders->lockForUpdate($orderId);

        if (! $order->status->releasesStock() && ! self::reachedTheBuyer($order->status)) {
            $this->inventory->restock(self::quantities($order->items), StockMovementType::CANCEL, $order, $userId);
        }

        $sold = SalesCounter::counts($order, $order->status) ? self::quantities($order->items) : [];

        $this->orders->delete($order);

        return $sold;
    }

    /**
     * What a line keeps about its product: the SKU, the name in every language (a variant's is its
     * parent's) and the options (color, size...) in every language. Needs SNAPSHOT_RELATIONS loaded.
     *
     * @return array{sku: ?string, product_name: array<string, string>, options: list<array{name: array<string, string>, value: array<string, string>}>}
     */
    public function snapshot(Product $product): array
    {
        $names = fn ($model) => $model->translations->pluck('name', 'locale')->filter()->all();

        return [
            'sku' => $product->sku,
            'product_name' => $names($product->parent ?? $product),
            'options' => $product->attributeValues
                ->sortBy('attribute_id')
                ->map(fn (AttributeValue $value) => ['name' => $names($value->attribute), 'value' => $names($value)])
                ->values()
                ->all(),
        ];
    }

    /**
     * What a product costs on a new line right now.
     */
    public function currentPrice(Product $product): float
    {
        return $this->pricing->for($product)->amount;
    }

    /**
     * The stock follows the order. While the order holds its stock, an edit sells only the
     * difference between the saved and the new lines. Reaching a status that releases stock
     * (cancelled, rejected, returned) gives the order's lines back once. An order that already
     * gave its stock back moves nothing.
     *
     * @param  array<int, int>  $before  product id => quantity on the saved lines
     * @param  array<int, int>  $after  product id => quantity on the new lines
     */
    private function moveStock(Order $order, OrderStatus $old, OrderStatus $new, array $before, array $after, ?int $userId): void
    {
        if ($old->releasesStock()) {
            return;
        }

        $difference = [];
        foreach (array_keys($before + $after) as $productId) {
            $difference[$productId] = ($after[$productId] ?? 0) - ($before[$productId] ?? 0);
        }

        $this->inventory->sell($difference, $order, $userId);

        if ($new->releasesStock()) {
            $type = $new === OrderStatus::RETURNED ? StockMovementType::RETURN : StockMovementType::CANCEL;

            // Units a completed return request already gave back are not given back again.
            if ($new === OrderStatus::RETURNED) {
                $returned = $this->orders->returnedQuantities($order->id);

                foreach ($after as $productId => $quantity) {
                    $after[$productId] = max(0, $quantity - ($returned[$productId] ?? 0));
                }
            }

            $this->inventory->restock($after, $type, $order, $userId);
        }
    }

    /**
     * Quantity per product over these lines (order items or priced rows). Lines without a product
     * (deleted since) hold no stock.
     *
     * @param  iterable<OrderItem|array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private static function quantities(iterable $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $productId = (int) data_get($line, 'product_id');

            if ($productId > 0) {
                $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) data_get($line, 'quantity');
            }
        }

        return $quantities;
    }

    /**
     * Whether the coupon the order used still covers its lines as they are (true when it used none).
     * The edit page warns when it does not: saving would set its discount to 0.
     */
    public function couponStillApplies(Order $order): bool
    {
        return blank($order->coupon_code) || $this->couponDiscountOrNull((string) $order->coupon_code, $order->items->all()) !== null;
    }

    /**
     * The coupon's discount on these lines, with the checkout's rules (its product or category, its
     * minimum), or 0 when its offer is gone or it no longer applies.
     *
     * @param  iterable<OrderItem|array<string, mixed>>  $lines
     */
    private function couponDiscount(string $code, iterable $lines): float
    {
        return $this->couponDiscountOrNull($code, $lines) ?? 0.0;
    }

    /**
     * @param  iterable<OrderItem|array<string, mixed>>  $lines
     */
    private function couponDiscountOrNull(string $code, iterable $lines): ?float
    {
        $lines = collect($lines);
        // A set's piece is matched as the set it was sold in (the product the coupon may name).
        $sold = fn ($line) => (int) (data_get($line, 'bundle_product_id') ?: data_get($line, 'product_id'));
        $products = $this->products->forCart($lines->map($sold)->filter()->unique()->values()->all());

        $facts = $lines->map(function ($line) use ($products, $sold): array {
            $product = $products->get($sold($line));
            $sellable = $product?->parent ?? $product;

            return [
                'total' => (float) data_get($line, 'total_price'),
                'sellable_id' => $sellable?->id,
                'category_ids' => $sellable ? array_map('intval', $sellable->categories->modelKeys()) : [],
            ];
        })->values()->all();

        return $this->coupons->forOrder($code, $facts)?->discount();
    }

    /**
     * One row of the order's status history: the timeline the buyer tracks.
     */
    private function recordStatus(Order $order, OrderStatus $status, ?int $userId): void
    {
        $order->statusHistory()->create(['status' => $status, 'created_by' => $userId]);
    }

    /**
     * A set or bundle's line as order lines, one per piece: the variant the buyer chose, $quantity
     * sets times the piece's quantity, the set's price shared out over the pieces in proportion to
     * their own store prices (BundleAllocation), and the set's snapshot on each (`bundle_group` ties
     * them together).
     *
     * @param  list<array{product_id: int, quantity: int}>  $components  one set's pieces
     * @param  Collection<int, Product>  $products  with SNAPSHOT_RELATIONS, by id
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException on `items`
     */
    private function bundleLines(Product $bundle, int $quantity, float $price, array $components, Collection $products): array
    {
        $pieces = [];

        foreach (array_values($components) as $index => $component) {
            $item = $products->get((int) $component['product_id']);

            if ($item === null || $item->isBundle()) {
                throw ValidationException::withMessages(['items' => __('This product is no longer available.')]);
            }

            $pieces[$index] = ['item' => $item, 'quantity' => max(1, (int) $component['quantity'])];
        }

        $split = BundleAllocation::split($price, array_map(fn (array $piece) => [
            'quantity' => $piece['quantity'],
            'weight' => $this->currentPrice($piece['item']),
        ], $pieces));

        $snapshot = [
            'type' => $bundle->bundle_type,
            'sku' => $bundle->sku,
            'name' => $bundle->translations->pluck('name', 'locale')->filter()->all(),
            'quantity' => $quantity,
            'unit_price' => round($price, 2),
        ];
        $group = (string) Str::uuid();

        return array_map(fn (array $piece, int $index) => [
            'product_id' => (int) $piece['item']->id,
            'quantity' => $piece['quantity'] * $quantity,
            'price' => $split[$index]['unit'],
            'total_price' => round($split[$index]['unit'] * $piece['quantity'] * $quantity + $split[$index]['extra'] * $quantity, 2),
            'bundle_product_id' => (int) $bundle->id,
            'bundle_group' => $group,
            'bundle' => $snapshot,
        ], $pieces, array_keys($pieces));
    }

    /**
     * A set or bundle row never goes on an order as itself: its pieces do (checkout()).
     *
     * @param  Collection<int, Product>  $products
     *
     * @throws ValidationException on `items`
     */
    private static function assertNoBundleRows(Collection $products): void
    {
        if ($products->contains(fn (Product $product) => $product->isBundle())) {
            throw ValidationException::withMessages(['items' => __('A set or bundle is sold on the storefront only.')]);
        }
    }

    /**
     * The pieces of a set sold on the order stay as they were sold (same products and quantities),
     * or go all together: a set is never half removed or changed.
     *
     * @param  array<int, array<string, mixed>>  $rows  the form's lines
     * @param  Collection<int, OrderItem>  $kept  the saved lines, by id
     *
     * @throws ValidationException on `items`
     */
    private static function assertBundlesWhole(array $rows, Collection $kept): void
    {
        $sent = collect($rows)->filter(fn (array $row) => filled($row['id'] ?? null))->keyBy(fn (array $row) => (int) $row['id']);

        foreach ($kept->filter(fn (OrderItem $item) => $item->bundle_group !== null)->groupBy('bundle_group') as $pieces) {
            $present = $pieces->filter(fn (OrderItem $item) => $sent->has($item->id));

            if ($present->isEmpty()) {
                continue; // the whole set removed
            }

            $changed = $present->count() !== $pieces->count() || $present->contains(function (OrderItem $item) use ($sent): bool {
                $row = $sent->get($item->id);

                return (int) ($row['product_id'] ?? $item->product_id) !== (int) $item->product_id || (int) $row['quantity'] !== (int) $item->quantity;
            });

            if ($changed) {
                throw ValidationException::withMessages(['items' => __('The pieces of a set stay as they were sold: keep the whole set, or remove all its pieces.')]);
            }
        }
    }

    /**
     * Whether the order's lines can no longer change (see assertLinesUnchanged).
     */
    public function linesLocked(Order $order): bool
    {
        return self::reachedTheBuyer($order->status) || $this->orders->hasReturnRequests($order->id);
    }

    /**
     * The goods reached the buyer: delivered, or exchanged or returned after that.
     */
    private static function reachedTheBuyer(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::DELIVERED, OrderStatus::EXCHANGED, OrderStatus::RETURNED], true);
    }

    /**
     * Once the goods reached the buyer, or a return request exists, the lines are what was
     * delivered: returns, refunds and stock count on them. The same lines (ids, products and
     * quantities) may be sent again with other fields changed; anything else is refused.
     *
     * @param  array<int, array<string, mixed>>  $rows  the form's lines
     * @param  Collection<int, OrderItem>  $kept  the saved lines, by id
     *
     * @throws ValidationException on `items`
     */
    private function assertLinesUnchanged(Order $order, array $rows, Collection $kept): void
    {
        if (! self::reachedTheBuyer($order->status) && ! $this->orders->hasReturnRequests($order->id)) {
            return;
        }

        $sent = collect($rows)->map(fn (array $row) => [(int) ($row['id'] ?? 0), (int) ($row['product_id'] ?? $kept->get($row['id'] ?? null)?->product_id), (int) $row['quantity']])->sortBy(0)->values()->all();
        $saved = $kept->map(fn (OrderItem $item) => [(int) $item->id, (int) $item->product_id, (int) $item->quantity])->sortBy(0)->values()->all();

        if ($sent !== $saved) {
            throw ValidationException::withMessages(['items' => __('The items of this order can no longer change: it was delivered, or it has a return request.')]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => __('Invalid status transition from :current to :new', [
                'current' => $from->getLabel(),
                'new' => $to->getLabel(),
            ])]);
        }
    }

    /**
     * The form's rows with their money. A row keeps its saved price unless its product changed; a
     * new or changed row is priced by the OrderLinePricer (the store's prices unless a module binds
     * its own), which sees the whole order as it will be.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, OrderItem>  $saved
     * @return list<array<string, mixed>>
     */
    private function pricedLines(array $rows, Collection $saved, ?Order $order = null): array
    {
        ksort($rows);

        $productIdOf = fn (array $row) => $row['product_id'] ?? $saved->get($row['id'] ?? null)?->product_id;
        $quantities = self::quantities(array_map(fn (array $row) => ['product_id' => $productIdOf($row), 'quantity' => (int) $row['quantity']], $rows));

        $products = $this->products->query()
            ->with(self::SNAPSHOT_RELATIONS)
            ->whereKey(array_keys($quantities))
            ->get()
            ->keyBy('id');

        $prices = $this->linePricer->prices($order, $products, $quantities);

        return array_values(array_map(function (array $row) use ($products, $saved, $prices, $productIdOf): array {
            /** @var OrderItem|null $item */
            $item = $saved->get($row['id'] ?? null);
            $productId = $productIdOf($row);
            $quantity = (int) $row['quantity'];

            $samePrice = $item && (int) $item->product_id === (int) $productId;

            // A set is sold on the storefront, through its pieces: never as a line of its own.
            if (! $samePrice && $products->get($productId)?->isBundle()) {
                throw ValidationException::withMessages(['items' => __('A set or bundle is sold on the storefront only.')]);
            }

            // A set's piece keeps the share of the set's price it was sold at (assertBundlesWhole).
            if ($samePrice && $item->bundle_group !== null) {
                return [
                    'id' => $item->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => (float) $item->price,
                    'total_price' => (float) $item->total_price,
                    'bundle_product_id' => $item->bundle_product_id,
                ];
            }
            $price = $samePrice ? (float) $item->price : (float) ($prices[(int) $productId] ?? $this->currentPrice($products->get($productId)));
            // Addons chosen through the API stay part of the line.
            $addons = $samePrice ? $item->addonOptions->sum(fn (AddonOption $option) => $option->pivot->price * $option->pivot->quantity) : 0;

            return [
                'id' => $item?->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'total_price' => round($price * $quantity + $addons, 2),
                // A line keeps what it sold while its product stays the same.
                ...($samePrice ? [] : $this->snapshot($products->get($productId))),
            ];
        }, $rows));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $data
     * @return array{sub_total: float, discount: float, delivery_fee: float, total: float}
     *
     * @throws ValidationException
     */
    private function totals(array $lines, float $discount, array $data): array
    {
        $subTotal = round(array_sum(array_column($lines, 'total_price')), 2);
        $delivered = (int) ($data['delivery_type'] ?? DeliveryType::DELIVERY->value) === DeliveryType::DELIVERY->value;
        $fee = $delivered ? round((float) ($data['delivery_fee'] ?? 0), 2) : 0.0;
        // A discount never goes past what the lines cost.
        $discount = round(min(max($discount, 0), $subTotal), 2);
        $total = round($subTotal - $discount + $fee, 2);

        if ($total > self::MONEY_LIMIT || $subTotal > self::MONEY_LIMIT) {
            throw ValidationException::withMessages(['items' => __('The order total is too large.')]);
        }

        return ['sub_total' => $subTotal, 'discount' => $discount, 'delivery_fee' => $fee, 'total' => $total];
    }

    /**
     * The buyer is told once the outermost transaction commits (a return request's completion
     * holds this one): a failing insert inside it would abort PostgreSQL's transaction, and a
     * rollback would leave a message about a change that never happened.
     */
    private function notifyAfterCommit(Order $order, OrderStatus $status): void
    {
        DB::afterCommit(fn () => $this->notifyStatusChanged($order, $status));
    }

    /**
     * After the commit, and never fatal: the order has changed whether or not the message can go. A
     * store with no Firebase credentials fails here, after the database notification is written.
     */
    private function notifyStatusChanged(Order $order, OrderStatus $status): void
    {
        try {
            $order->loadMissing('user');

            $order->user?->notify(new GeneralNotification(
                title: __('Order Status Updated'),
                message: __('Your order #:order_number status is now :status', [
                    'order_number' => $order->order_number,
                    'status' => $status->getLabel(),
                ]),
            ));
        } catch (Throwable $e) {
            Log::warning('Order status notification not sent: '.$e->getMessage(), ['order' => $order->id]);
        }
    }
}
