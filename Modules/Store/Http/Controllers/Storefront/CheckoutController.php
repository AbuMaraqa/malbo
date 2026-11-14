<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Support\SubmitOnce;
use Modules\Store\Contracts\PaymentGateway;
use Modules\Store\Entities\Address;
use Modules\Store\Http\Requests\CheckoutRequest;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Services\CartService;
use Modules\Store\Services\CheckoutService;
use Modules\Store\Services\Marketing\MetaEvents;
use Modules\Store\Support\Price;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Checkout, as a guest or signed in (the form then starts from the account and its default
 * address, and the order is the account's). The page reloads only `quote` when the city or the coupon changes; every amount
 * comes from CheckoutService. The form carries a one-time token, and the cart itself is locked
 * while it is ordered, so a double submit places one order. The confirmation shows only the order
 * placed in this session.
 */
class CheckoutController extends Controller
{
    private const PLACED = 'store.placed_order';

    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
        private readonly ShippingZoneRepositoryInterface $zones,
        private readonly OrderRepositoryInterface $orders,
        private readonly StorefrontLayout $layout,
        private readonly AddressRepositoryInterface $addresses,
        private readonly MetaEvents $events,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $cart = $this->cart->current($request);
        $lines = $cart ? $this->cart->lines($cart) : [];

        if ($lines === [] || collect($lines)->contains(fn (array $line) => ! $line['available'])) {
            return to_route('store.cart');
        }

        $cityId = is_numeric($request->query('city_id')) ? (int) $request->query('city_id') : null;
        $coupon = is_string($request->query('coupon')) ? mb_substr($request->query('coupon'), 0, 50) : null;

        return Inertia::render('Store/Storefront/Checkout', [
            ...$this->layout->props(['title' => __('Checkout'), 'noindex' => true], fn () => [$this->events->initiateCheckout($request, $lines, ['email' => $cart->contact_email, 'phone' => $cart->contact_phone])]),
            'lines' => fn () => array_map(fn (array $line) => [
                'id' => $line['id'],
                'name' => $line['name'],
                'options' => $line['options'],
                'bundle' => $line['bundle'] ?? false,
                'image' => $line['image'],
                'quantity' => $line['quantity'],
                'total_label' => $line['total_label'],
            ], $lines),
            'cities' => fn () => $this->zones->deliverableCities(),
            'quote' => fn () => $this->checkout->quote($lines, $cityId, $coupon, ip: (string) $request->ip(), userId: $request->user()?->id),
            // Signed in: the form starts from the account and its default address.
            'account' => fn () => $request->user() ? [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone' => $request->user()->phone,
            ] : null,
            'addresses' => fn () => $request->user() ? $this->deliverableAddresses((int) $request->user()->id) : [],
            'paymentMethods' => fn () => $this->checkout->paymentMethods(),
            'submitToken' => fn () => (string) Str::uuid(),
        ]);
    }

    public function place(CheckoutRequest $request): HttpResponse
    {
        $data = $request->validated();
        $cart = $this->cart->current($request);

        if ($cart === null) {
            return $this->alreadyPlaced($request) ?? to_route('store.cart');
        }

        /** @var Order|null $order */
        $order = null;
        $user = $request->user();
        SubmitOnce::run('checkout', $data['submit_token'], function () use ($cart, $data, $user, &$order): void {
            $order = $this->checkout->place($cart, $data, $user);
        });

        if ($order === null) {
            return $this->alreadyPlaced($request) ?? back()->with('error', __('Your order is being placed. Please wait a moment.'));
        }

        $request->session()->put(self::PLACED, $order->id);

        if ($data['payment_method'] === CheckoutService::ONLINE && app()->bound(PaymentGateway::class)) {
            return Inertia::location(app(PaymentGateway::class)->start($order));
        }

        return to_route('store.checkout.done');
    }

    public function done(Request $request): Response|RedirectResponse
    {
        $id = $request->session()->get(self::PLACED);
        /** @var Order|null $order */
        $order = is_int($id) ? $this->orders->find($id) : null;

        if ($order === null) {
            return to_route('store.track');
        }

        return Inertia::render('Store/Storefront/CheckoutDone', [
            ...$this->layout->props(['title' => __('Thank you for your order'), 'noindex' => true], fn () => [$this->events->purchase($request, $order)]),
            'order' => [
                'number' => $order->order_number,
                'total_label' => (new Price((float) $order->total))->format(),
                'status' => $order->status->getLabel(),
                'awaiting_confirmation' => $order->status === OrderStatus::AWAITING_CONFIRMATION,
                'track_url' => route('store.track', ['number' => $order->order_number]),
            ],
        ]);
    }

    /**
     * The account's saved addresses checkout can deliver to (their city is in an active zone), the
     * default first.
     *
     * @return list<array<string, mixed>>
     */
    private function deliverableAddresses(int $userId): array
    {
        $cities = array_column($this->zones->deliverableCities(), 'value');

        return $this->addresses->forUser($userId)
            ->filter(fn (Address $address) => in_array((int) $address->city_id, $cities, true))
            ->map(fn (Address $address) => AddressController::row($address))
            ->values()
            ->all();
    }

    /**
     * This browser's cart was already ordered in this session (a second submit): its confirmation.
     */
    private function alreadyPlaced(Request $request): ?RedirectResponse
    {
        $orderId = $this->cart->latest($request)?->order_id;

        if ($orderId !== null && (int) $orderId === (int) $request->session()->get(self::PLACED)) {
            return to_route('store.checkout.done');
        }

        return null;
    }
}
