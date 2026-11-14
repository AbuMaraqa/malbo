<?php

namespace Modules\Store\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Notifications\NewOrderAlert;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Services\OrderService;
use Modules\Store\Contracts\PaymentGateway;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\ShippingZone;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Settings\StoreShippingSettings;
use Modules\Store\Support\Coupon;
use Modules\Store\Support\Phone;
use Modules\Store\Support\Price;

/**
 * Checkout's money and the order it places. Everything is computed here, from the cart's lines
 * priced now, the city's shipping zone and the coupon; the browser only says which city and which
 * code. The delivery fee is the zone's, 0 once the subtotal after discount reaches the zone's
 * free-delivery threshold (else the store's), or with a free-shipping coupon. A city in no active
 * zone gets no delivery, and checkout refuses it.
 */
class CheckoutService
{
    public const CASH = 'cash';

    public const ONLINE = 'online';

    /** Coupon codes tried a minute from one address: enough for a buyer, too few to guess codes. */
    public const COUPON_ATTEMPTS = 10;

    public function __construct(
        private readonly CartService $cart,
        private readonly CartRepositoryInterface $carts,
        private readonly CouponService $coupons,
        private readonly ShippingZoneRepositoryInterface $zones,
        private readonly OrderService $orders,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AddressService $addresses,
    ) {}

    /**
     * The payment methods checkout offers: cash on delivery, and online payment once a gateway is
     * bound.
     *
     * @return list<array{value: string, label: string}>
     */
    public function paymentMethods(): array
    {
        $methods = [['value' => self::CASH, 'label' => __('Cash on delivery')]];

        if (app()->bound(PaymentGateway::class)) {
            $methods[] = ['value' => self::ONLINE, 'label' => app(PaymentGateway::class)->label()];
        }

        return $methods;
    }

    /**
     * What the order would cost now. An invalid coupon is reported, not thrown. With $ip, codes
     * tried from that address are limited (COUPON_ATTEMPTS a minute).
     *
     * @param  list<array<string, mixed>>  $lines  CartService::lines()
     * @return array<string, mixed>
     */
    public function quote(array $lines, ?int $cityId, ?string $couponCode, ?string $phone = null, ?string $ip = null, ?int $userId = null): array
    {
        $subtotal = CartService::subtotal($lines);
        $zone = $cityId ? $this->zones->activeForCity($cityId) : null;
        $coupon = null;
        $couponState = null;

        $throttle = $ip === null ? null : 'store-coupon:'.$ip;

        if (filled($couponCode) && $throttle !== null && RateLimiter::tooManyAttempts($throttle, self::COUPON_ATTEMPTS)) {
            $couponState = ['code' => trim((string) $couponCode), 'valid' => false, 'message' => __('Too many coupon attempts. Try again in a minute.')];
        } elseif (filled($couponCode)) {
            if ($throttle !== null) {
                RateLimiter::hit($throttle, 60);
            }

            try {
                $coupon = $this->coupons->check((string) $couponCode, $lines, $phone, userId: $userId);
                $couponState = ['code' => $coupon->code, 'valid' => true, 'message' => null];
            } catch (ValidationException $e) {
                $couponState = ['code' => trim((string) $couponCode), 'valid' => false, 'message' => $e->errors()['coupon'][0] ?? null];
            }
        }

        return [
            ...$this->figures($subtotal, $zone, $coupon),
            'coupon' => $couponState,
            'city_chosen' => $cityId !== null,
            'deliverable' => $zone !== null,
            'zone' => $zone ? [
                'name' => (string) $zone->name,
                'fee_label' => (new Price((float) $zone->fee))->format(),
                'eta' => $zone->etaLabel(),
            ] : null,
        ];
    }

    /**
     * Places the cart as an order, in one transaction: the cart is locked (a second submit waits,
     * then gets the same order back), its lines are checked and priced again, the coupon is locked
     * and checked, the order is written by OrderService (which takes the stock) and the cart is
     * closed. The staff are told once the response is out.
     *
     * A signed-in buyer's order carries their account, and nothing else does: a phone typed here
     * links no other order (a guest order joins an account only through AccountOrders::claim). The
     * address is saved when they ask (skipped, not refused, once they keep AddressService::LIMIT).
     *
     * @param  array<string, mixed>  $data  CheckoutRequest's fields
     *
     * @throws ValidationException
     */
    public function place(Cart $cart, array $data, ?User $user = null): Order
    {
        $placed = false;

        $order = DB::transaction(function () use ($cart, $data, $user, &$placed): Order {
            $locked = $this->carts->lock($cart->id);

            if ($locked?->order_id) {
                /** @var Order */
                return $this->orderRepository->findOrFail($locked->order_id);
            }

            if ($locked === null || $locked->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('Your cart is empty.')]);
            }

            $lines = $this->cart->lines($locked);
            $problems = [];

            foreach ($lines as $line) {
                if (! $line['available']) {
                    $problems['items.'.$line['id']] = $line['name'].': '.$line['problem'];
                }
            }

            if ($problems !== []) {
                throw ValidationException::withMessages($problems);
            }

            $zone = $this->zones->activeForCity((int) $data['city_id']);

            if ($zone === null) {
                throw ValidationException::withMessages(['city_id' => __('We do not deliver to this city yet.')]);
            }

            $subtotal = CartService::subtotal($lines);
            $coupon = filled($data['coupon'] ?? null)
                ? $this->coupons->check((string) $data['coupon'], $lines, (string) $data['phone'], lock: true, userId: $user?->id)
                : null;
            $figures = $this->figures($subtotal, $zone, $coupon);
            $online = ($data['payment_method'] ?? self::CASH) === self::ONLINE;

            $phone = Phone::canonical((string) $data['phone']);

            $order = $this->orders->checkout([
                'user_id' => $user?->id,
                'customer_name' => $data['name'],
                'customer_email' => $data['email'] ?? null,
                'contact_phone' => $phone,
                'delivery_address' => $data['address'],
                'city_id' => (int) $data['city_id'],
                'district_id' => filled($data['district_id'] ?? null) ? (int) $data['district_id'] : null,
                'notes' => $data['notes'] ?? null,
                'payment_method' => ($online ? PaymentMethod::CREDIT_CARD : PaymentMethod::CASH)->value,
                'delivery_fee' => $figures['delivery_fee'],
                'discount' => $figures['discount'],
                'coupon_code' => $coupon?->code,
            ], array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'price' => $line['unit_price'],
                // A set or bundle: OrderService writes one line per piece chosen.
                ...(! empty($line['bundle']) ? ['components' => $line['components']] : []),
            ], $lines));

            if ($user !== null) {
                if (! empty($data['save_address']) && $this->addresses->hasRoom($user)) {
                    $this->addresses->create($user, [
                        'label' => $data['address_label'] ?? null,
                        'recipient_name' => $data['name'],
                        'phone' => $phone,
                        'city_id' => (int) $data['city_id'],
                        'district_id' => $data['district_id'] ?? null,
                        'address_line' => $data['address'],
                        'is_default' => false,
                    ]);
                }
            }

            $this->cart->markConverted($locked, $order);
            $placed = true;

            return $order;
        });

        if ($placed) {
            // After the response, not on a queue (no worker is guaranteed). Never fatal: the alert
            // catches its own failures.
            defer(fn () => NewOrderAlert::send($order), always: true);
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function figures(float $subtotal, ?ShippingZone $zone, ?Coupon $coupon): array
    {
        $discount = min($coupon?->discount() ?? 0.0, $subtotal);
        $afterDiscount = round($subtotal - $discount, 2);

        $threshold = $zone?->free_shipping_threshold !== null
            ? (float) $zone->free_shipping_threshold
            : app(StoreShippingSettings::class)->default_free_shipping_threshold;
        $reached = $threshold !== null && $afterDiscount >= $threshold;
        $free = $reached || (bool) $coupon?->freeShipping();

        $fee = $zone && ! $free ? round((float) $zone->fee, 2) : 0.0;
        $total = round($afterDiscount + $fee, 2);
        $remaining = $threshold !== null && ! $reached ? round($threshold - $afterDiscount, 2) : null;

        return [
            'subtotal' => $subtotal,
            'subtotal_label' => (new Price($subtotal))->format(),
            'discount' => $discount,
            'discount_label' => (new Price($discount))->format(),
            'delivery_fee' => $fee,
            'delivery_fee_label' => $zone ? ($fee > 0 ? (new Price($fee))->format() : __('Free')) : null,
            'free_shipping' => $free,
            'free_shipping_threshold_label' => $threshold !== null ? (new Price($threshold))->format() : null,
            'free_shipping_remaining_label' => $remaining !== null ? (new Price($remaining))->format() : null,
            'free_shipping_progress' => $threshold ? (int) min(100, floor($afterDiscount / $threshold * 100)) : null,
            'total' => $total,
            'total_label' => (new Price($total))->format(),
        ];
    }
}
