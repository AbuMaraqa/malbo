<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

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
use Modules\Store\Entities\Address;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;
use Modules\Store\Support\Price;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\PaymentTerms;
use Modules\Wholesale\Http\Requests\WholesaleCheckoutRequest;
use Modules\Wholesale\Services\MerchantCredit;
use Modules\Wholesale\Services\WholesaleCartService;
use Modules\Wholesale\Services\WholesaleCheckoutService;
use Modules\Wholesale\Support\PortalNav;

/**
 * Wholesale checkout (approved merchants only): where it goes, notes and payment terms; every amount
 * comes from WholesaleCartService. A one-time token and the locked cart make a double submit place
 * one order. The confirmation shows only the order placed in this session.
 */
class CheckoutController extends Controller
{
    private const PLACED = 'wholesale.placed_order';

    public function __construct(
        private readonly WholesaleCartService $cartService,
        private readonly WholesaleCheckoutService $checkoutService,
        private readonly AddressRepositoryInterface $addresses,
        private readonly OrderRepositoryInterface $orders,
        private readonly StorefrontLayout $layout,
        private readonly MerchantCredit $credit,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $merchant = $this->merchant($request);
        $cart = $this->cartService->current((int) $merchant->user_id);
        $read = $cart ? $this->cartService->read($cart, $merchant) : null;

        if ($read === null || ! $read['summary']['can_checkout']) {
            return to_route('store.wholesale.cart');
        }

        return Inertia::render('Wholesale/Portal/Checkout', [
            ...$this->layout->props(['title' => __('Wholesale checkout'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'groups' => $read['groups'],
            'summary' => $read['summary'],
            'shop' => [
                'name' => $merchant->shop_name,
                'address' => $merchant->shop_address,
                'city' => $merchant->city?->name,
            ],
            'addresses' => $this->addresses->forUser((int) $merchant->user_id)->map(fn (Address $address) => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'address_line' => $address->address_line,
                'phone' => $address->phone,
                'is_default' => (bool) $address->is_default,
            ])->values()->all(),
            'addressesUrl' => route('store.account.addresses'),
            'paymentTerms' => PaymentTerms::options($merchant),
            // The merchant's credit, when it may order on credit (null: not offered).
            'credit' => $this->credit->summary($merchant),
            'submitToken' => (string) Str::uuid(),
        ]);
    }

    public function place(WholesaleCheckoutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $merchant = $this->merchant($request);
        $cart = $this->cartService->current((int) $merchant->user_id);

        if ($cart === null) {
            return $this->alreadyPlaced($request) ?? to_route('store.wholesale.cart');
        }

        /** @var Order|null $order */
        $order = null;
        SubmitOnce::run('wholesale-checkout', $data['submit_token'], function () use ($cart, $merchant, $data, &$order): void {
            $order = $this->checkoutService->place($cart, $merchant, $data);
        });

        if ($order === null) {
            return $this->alreadyPlaced($request) ?? back()->with('error', __('Your order is being placed. Please wait a moment.'));
        }

        $request->session()->put(self::PLACED, $order->id);

        return to_route('store.wholesale.checkout.done');
    }

    public function done(Request $request): Response|RedirectResponse
    {
        $id = $request->session()->get(self::PLACED);
        /** @var Order|null $order */
        $order = is_int($id) ? $this->orders->find($id) : null;

        if ($order === null || (int) $order->user_id !== (int) $request->user()->id) {
            return to_route('store.wholesale.orders');
        }

        return Inertia::render('Wholesale/Portal/CheckoutDone', [
            ...$this->layout->props(['title' => __('Thank you for your order'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'order' => [
                'number' => $order->order_number,
                'total_label' => (new Price((float) $order->total))->format(),
                'status' => $order->status->getLabel(),
                'awaiting_confirmation' => $order->status === OrderStatus::AWAITING_CONFIRMATION,
                'payment' => $order->payment_method?->getLabel(),
                'url' => route('store.wholesale.orders.show', ['number' => $order->order_number]),
            ],
        ]);
    }

    /**
     * The merchant's cart was already ordered in this session (a second submit): its confirmation.
     */
    private function alreadyPlaced(Request $request): ?RedirectResponse
    {
        return is_int($request->session()->get(self::PLACED)) ? to_route('store.wholesale.checkout.done') : null;
    }

    private function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }
}
