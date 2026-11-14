<?php

namespace Modules\Wholesale\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Notifications\NewOrderAlert;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Services\OrderService;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Enums\PaymentTerms;
use Modules\Wholesale\Repositories\Contracts\WholesaleCartRepositoryInterface;

/**
 * Places a merchant's wholesale cart as a B2B order, in one transaction: the cart is locked (a
 * second submit waits, then gets the same order back), read and priced again, and refused while a
 * line has a problem, a size run is broken or the minimum order is not reached; on credit, also
 * when it does not fit in the merchant's credit (MerchantCredit, on the locked merchant row). OrderService writes
 * the order (channel B2B, awaiting MALBO's confirmation, delivery fee 0: MALBO arranges wholesale
 * delivery and staff may set a fee later), and InventoryService takes the stock from the one
 * inventory the storefront sells from too.
 */
class WholesaleCheckoutService
{
    public const SHOP = 'shop';

    public const SAVED = 'saved';

    public function __construct(
        private readonly WholesaleCartService $cart,
        private readonly WholesaleCartRepositoryInterface $carts,
        private readonly OrderService $orders,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AddressRepositoryInterface $addresses,
        private readonly MerchantCredit $credit,
    ) {}

    /**
     * @param  array{address_source: string, address_id?: ?int, notes?: ?string, payment_terms: string}  $data
     *
     * @throws ValidationException
     */
    public function place(WholesaleCart $cart, Merchant $merchant, array $data): Order
    {
        $placed = false;
        $terms = PaymentTerms::from($data['payment_terms']);
        $method = $terms->paymentMethod();

        if (! in_array($terms, PaymentTerms::offeredTo($merchant), true)) {
            throw ValidationException::withMessages(['payment_terms' => __('Choose how you will pay.')]);
        }

        $delivery = $this->delivery($merchant, $data);

        $order = DB::transaction(function () use ($cart, $merchant, $data, $terms, $method, $delivery, &$placed): Order {
            $locked = $this->carts->lock($cart->id);

            if ($locked?->order_id) {
                /** @var Order */
                return $this->orderRepository->findOrFail($locked->order_id);
            }

            if ($locked?->converted_at !== null) {
                // Ordered once, its order deleted since: never ordered twice.
                throw ValidationException::withMessages(['checkout' => __('This cart was already ordered.')]);
            }

            if ($locked === null || $locked->items->isEmpty()) {
                throw ValidationException::withMessages(['checkout' => __('Your cart is empty.')]);
            }

            $read = $this->cart->read($locked, $merchant);
            $problems = [];

            foreach ($read['groups'] as $group) {
                if ($group['problem'] !== null) {
                    $problems['groups.'.$group['product_id']] = $group['name'].': '.$group['problem'];
                }

                foreach ($group['lines'] as $line) {
                    if ($line['problem'] !== null) {
                        $problems['lines.'.$line['id']] = $group['name'].': '.$line['problem'];
                    }
                }
            }

            if ($problems === [] && $read['summary']['too_large'] !== null) {
                $problems['checkout'] = $read['summary']['too_large'];
            }

            if ($problems === [] && $read['summary']['minimum_missing'] !== []) {
                $problems['minimum'] = implode(' ', $read['summary']['minimum_missing']);
            }

            if ($problems !== []) {
                throw ValidationException::withMessages($problems);
            }

            if ($terms === PaymentTerms::CREDIT) {
                // On the locked merchant row: balance + open orders + this one within the limit.
                $this->credit->assertRoom($merchant, (float) $read['summary']['subtotal']);
            }

            $lines = [];

            foreach ($read['groups'] as $group) {
                foreach ($group['lines'] as $line) {
                    $lines[] = ['product_id' => $line['product_id'], 'quantity' => $line['quantity'], 'price' => $line['unit_price']];
                }
            }

            $order = $this->orders->checkout([
                'user_id' => $merchant->user_id,
                'customer_name' => $merchant->shop_name,
                'customer_email' => $merchant->email,
                ...$delivery,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'payment_method' => $method->value,
                'delivery_fee' => 0,
                'discount' => 0,
                'coupon_code' => null,
            ], $lines, OrderChannel::B2B);

            $this->cart->markConverted($locked, $order);
            $placed = true;

            return $order;
        });

        if ($placed) {
            defer(fn () => NewOrderAlert::send($order), always: true);
        }

        return $order;
    }

    /**
     * Where it goes: one of the merchant's saved addresses, or the shop's address.
     *
     * @param  array<string, mixed>  $data
     * @return array{contact_phone: string, delivery_address: string, city_id: ?int, district_id: ?int}
     *
     * @throws ValidationException on `address_id` or `address_source`
     */
    private function delivery(Merchant $merchant, array $data): array
    {
        if (($data['address_source'] ?? null) === self::SAVED) {
            try {
                $address = $this->addresses->findForUser((int) $merchant->user_id, (int) ($data['address_id'] ?? 0));
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages(['address_id' => __('Choose one of your addresses.')]);
            }

            return [
                'contact_phone' => (string) ($address->phone ?: $merchant->phone),
                'delivery_address' => (string) $address->address_line,
                'city_id' => $address->city_id ? (int) $address->city_id : null,
                'district_id' => $address->district_id ? (int) $address->district_id : null,
            ];
        }

        if (blank($merchant->shop_address)) {
            throw ValidationException::withMessages(['address_source' => __('Your shop has no address on file: choose a saved address or add one.')]);
        }

        return [
            'contact_phone' => (string) $merchant->phone,
            'delivery_address' => (string) $merchant->shop_address,
            'city_id' => $merchant->city_id ? (int) $merchant->city_id : null,
            'district_id' => null,
        ];
    }
}
