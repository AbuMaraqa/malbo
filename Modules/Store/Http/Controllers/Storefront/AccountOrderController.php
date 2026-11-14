<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Store\Http\Requests\TrackOrderRequest;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Services\AccountOrders;
use Modules\Store\Support\StorefrontLayout;

/**
 * The account's orders and an order's detail. An order the account does not see is a 404.
 */
class AccountOrderController extends Controller
{
    public function __construct(
        private readonly AccountOrders $orders,
        private readonly StorefrontLayout $layout,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Store/Storefront/Account/Orders', [
            ...$this->layout->props(['title' => __('My orders'), 'noindex' => true]),
            'orders' => fn () => $this->orders->page($request->user()),
        ]);
    }

    /**
     * "Add a past order to my account": its number and the phone it was placed with.
     */
    public function claim(TrackOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $order = $this->orders->claim($request->user(), (string) $request->ip(), $data['number'], $data['phone']);

        return to_route('store.account.orders.show', ['number' => $order->order_number])->with('success', __('The order was added to your account.'));
    }

    public function show(Request $request, string $number): Response
    {
        $order = $this->orders->find($request->user(), $number);

        abort_if($order === null, 404);

        return Inertia::render('Store/Storefront/Account/OrderDetail', [
            ...$this->layout->props(['title' => __('Order').' '.$order->order_number, 'noindex' => true]),
            'order' => fn () => $this->orders->detail($request->user(), $order),
        ]);
    }
}
