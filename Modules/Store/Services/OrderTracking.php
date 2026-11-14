<?php

namespace Modules\Store\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\OrderStatusHistory;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Services\Couriers\CourierRegistry;
use Modules\Items\Support\OrderBundles;
use Modules\Store\Support\Phone;
use Modules\Store\Support\Price;

/**
 * Order tracking for buyers without an account: an order number and the phone it was placed with.
 * A wrong pair, or a number that does not exist, gets the same message, and lookups are limited
 * per address. The result holds the status and its dates, the lines and the totals: never a name,
 * an address, a phone or a note.
 */
class OrderTracking
{
    /** Lookups a minute from one address. */
    public const ATTEMPTS = 10;

    /**
     * The buyer's path, in order; a final status ends it.
     *
     * @var array<string, list<OrderStatus>>
     */
    private const STAGES = [
        'placed' => [OrderStatus::PENDING, OrderStatus::AWAITING_CONFIRMATION],
        'confirmed' => [OrderStatus::CONFIRMED],
        'preparing' => [OrderStatus::PREPARING],
        'shipping' => [OrderStatus::OUT_FOR_DELIVERY, OrderStatus::READY_FOR_PICKUP],
        'delivered' => [OrderStatus::DELIVERED],
    ];

    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException on `number`: too many lookups, or no such order for this phone
     */
    public function lookup(string $ip, string $number, string $phone): array
    {
        $key = 'store-track:'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            throw ValidationException::withMessages(['number' => __('Too many attempts. Try again in a minute.')]);
        }

        RateLimiter::hit($key, 60);

        $order = $this->orders->findForTracking(trim($number));

        if ($order === null || ! Phone::same($order->contact_phone, $phone)) {
            throw ValidationException::withMessages(['number' => __('We could not find an order with this number and phone. Check them and try again.')]);
        }

        return $this->result($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function result(Order $order): array
    {
        $locale = app()->getLocale();
        $text = fn (?array $values) => (string) ($values[$locale] ?? collect($values ?? [])->first(fn ($value) => filled($value)) ?? '');
        $shipment = $order->shipment;

        return [
            'number' => $order->order_number,
            // Who carries the parcel and how to follow it with them, once it is shipped.
            'shipment' => $shipment ? [
                'courier' => app(CourierRegistry::class)->name($shipment->courier),
                'tracking_number' => $shipment->tracking_number,
            ] : null,
            'placed_at' => self::date($order->created_at),
            'status' => [
                'key' => strtolower($order->status->name),
                'label' => $order->status->getLabel(),
            ],
            'final' => $order->status->isFinal(),
            'timeline' => $this->timeline($order),
            'lines' => $order->items->map(fn (OrderItem $item) => [
                // A piece of a set: the set it came in ("Summer set ×2").
                'bundle' => OrderBundles::label($item, $locale),
                'name' => $text($item->product_name) ?: __('Product'),
                'options' => collect($item->options ?? [])->map(fn (array $option) => [
                    'name' => $text($option['name'] ?? null),
                    'value' => $text($option['value'] ?? null),
                ])->values()->all(),
                'quantity' => (int) $item->quantity,
                'total_label' => (new Price((float) $item->total_price))->format(),
            ])->values()->all(),
            'totals' => [
                'subtotal_label' => (new Price((float) $order->sub_total))->format(),
                'discount_label' => (float) $order->discount > 0 ? (new Price((float) $order->discount))->format() : null,
                'delivery_fee_label' => (float) $order->delivery_fee > 0 ? (new Price((float) $order->delivery_fee))->format() : __('Free'),
                'total_label' => (new Price((float) $order->total))->format(),
            ],
        ];
    }

    /**
     * The stages reached with the date each was first reached; the ones still to come while the
     * order is open; and the final status, when there is one, as its end.
     *
     * Each stage's tone: `done` or `pending` on the way, and for a final status `danger` (cancelled,
     * rejected, returned) or `info` (exchanged: the buyer got other items, nothing failed).
     *
     * @return list<array{key: string, label: string, reached: bool, current: bool, date: ?string, tone: string}>
     */
    private function timeline(Order $order): array
    {
        $history = $order->statusHistory;
        $current = $order->status;
        $stageOf = fn (OrderStatus $status): ?string => collect(self::STAGES)->search(fn (array $statuses) => in_array($status, $statuses, true)) ?: null;
        $keys = array_keys(self::STAGES);

        // How far along the path the order got: its current stage, or the furthest in its history.
        $reached = collect([$current, ...$history->pluck('status')])
            ->map(fn (OrderStatus $status) => $stageOf($status))
            ->filter()
            ->map(fn (string $stage) => array_search($stage, $keys, true))
            ->max() ?? 0;

        $currentStage = $stageOf($current);
        $timeline = [];

        foreach (self::STAGES as $key => $statuses) {
            $index = array_search($key, $keys, true);
            $isReached = $index <= $reached;

            if ($current->isFinal() && ! $isReached) {
                continue;
            }

            $row = $history->first(fn (OrderStatusHistory $row) => in_array($row->status, $statuses, true));

            $timeline[] = [
                'key' => $key,
                'label' => self::label($key),
                'reached' => $isReached,
                'current' => $key === $currentStage,
                'date' => $isReached ? self::date($row?->created_at ?? ($index === 0 ? $order->created_at : null)) : null,
                'tone' => $isReached ? 'done' : 'pending',
            ];
        }

        if ($current->isFinal()) {
            $row = $history->last(fn (OrderStatusHistory $row) => $row->status === $current);

            $timeline[] = [
                'key' => strtolower($current->name),
                'label' => $current->getLabel(),
                'reached' => true,
                'current' => true,
                'date' => self::date($row?->created_at ?? $order->updated_at),
                'tone' => $current === OrderStatus::EXCHANGED ? 'info' : 'danger',
            ];
        }

        return $timeline;
    }

    private static function label(string $stage): string
    {
        return match ($stage) {
            'placed' => __('Order received'),
            'confirmed' => __('Confirmed'),
            'preparing' => __('Preparing'),
            'shipping' => __('With courier'),
            'delivered' => __('Delivered'),
        };
    }

    private static function date(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i');
    }
}
