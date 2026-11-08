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
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
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
    ];

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly PricingService $pricing,
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

            foreach ($lines as $line) {
                $order->items()->create(Arr::only($line, ['product_id', 'price', 'quantity', 'total_price']));
            }

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $orderId, array $data): Order
    {
        /** @var array{0: Order, 1: OrderStatus, 2: OrderStatus} $result */
        $result = DB::transaction(function () use ($orderId, $data): array {
            $order = $this->orders->lockForUpdate($orderId);
            $old = $order->status;
            $new = OrderStatus::from((int) $data['status']);

            $this->assertTransition($old, $new);

            $kept = $order->items->keyBy('id');
            $lines = $this->pricedLines($data['items'], $kept);
            $totals = $this->totals($lines, (float) $order->discount, $data);

            $order->fill([...Arr::only($data, self::COLUMNS), ...$totals, 'status' => $new])->save();

            $incoming = array_filter(array_column($lines, 'id'));
            $kept->except($incoming)->each(fn (OrderItem $item) => $item->delete());

            foreach ($lines as $line) {
                $fields = Arr::only($line, ['product_id', 'price', 'quantity', 'total_price']);
                $item = $kept->get($line['id'] ?? null);

                $item ? $item->update($fields) : $order->items()->create($fields);
            }

            return [$order, $old, $new];
        });

        [$order, $old, $new] = $result;

        if ($old !== $new) {
            $this->notifyStatusChanged($order, $new);
        }

        return $order;
    }

    /**
     * Soft: an order is a financial record, and a permanent delete would take its lines with it.
     */
    public function delete(Order $order): void
    {
        $this->orders->delete($order);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->orders->query()->whereKey($ids)->get()->each(
            fn (Order $order) => $this->orders->delete($order)
        ));
    }

    /**
     * What a product costs on a new line right now.
     */
    public function currentPrice(Product $product): float
    {
        return $this->pricing->for($product)->amount;
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
     * The form's rows with their money. A row keeps its saved price unless its product changed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, OrderItem>  $saved
     * @return list<array<string, mixed>>
     */
    private function pricedLines(array $rows, Collection $saved): array
    {
        ksort($rows);

        $products = $this->products->query()
            ->whereKey(array_filter(array_column($rows, 'product_id')))
            ->get()
            ->keyBy('id');

        return array_values(array_map(function (array $row) use ($products, $saved): array {
            /** @var OrderItem|null $item */
            $item = $saved->get($row['id'] ?? null);
            $productId = $row['product_id'] ?? $item?->product_id;
            $quantity = (int) $row['quantity'];

            $samePrice = $item && (int) $item->product_id === (int) $productId;
            $price = $samePrice ? (float) $item->price : $this->currentPrice($products->get($productId));
            // Addons chosen through the API stay part of the line.
            $addons = $samePrice ? $item->addonOptions->sum(fn (AddonOption $option) => $option->pivot->price * $option->pivot->quantity) : 0;

            return [
                'id' => $item?->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'total_price' => round($price * $quantity + $addons, 2),
            ];
        }, $rows));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $data
     * @return array{sub_total: float, delivery_fee: float, total: float}
     *
     * @throws ValidationException
     */
    private function totals(array $lines, float $discount, array $data): array
    {
        $subTotal = round(array_sum(array_column($lines, 'total_price')), 2);
        $delivered = (int) ($data['delivery_type'] ?? DeliveryType::DELIVERY->value) === DeliveryType::DELIVERY->value;
        $fee = $delivered ? round((float) ($data['delivery_fee'] ?? 0), 2) : 0.0;
        $total = round(max(0, $subTotal - $discount) + $fee, 2);

        if ($total > self::MONEY_LIMIT || $subTotal > self::MONEY_LIMIT) {
            throw ValidationException::withMessages(['items' => __('The order total is too large.')]);
        }

        return ['sub_total' => $subTotal, 'delivery_fee' => $fee, 'total' => $total];
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
