<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Repositories\Contracts\WholesaleOrderRepositoryInterface;
use Modules\Wholesale\Services\MerchantOrders;
use Modules\Wholesale\Services\ReorderService;
use Modules\Wholesale\Support\PortalNav;

/**
 * The merchant's wholesale orders, one order's detail, and reorder (its lines back into the cart);
 * another account's order is a 404.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly MerchantOrders $orders,
        private readonly StorefrontLayout $layout,
        private readonly WholesaleOrderRepositoryInterface $orderRepository,
        private readonly ReorderService $reorder,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Wholesale/Portal/Orders', [
            ...$this->layout->props(['title' => __('My wholesale orders'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'orders' => fn () => $this->orders->page((int) $request->user()->id),
        ]);
    }

    public function show(Request $request, string $number): Response
    {
        $order = $this->orders->detail((int) $request->user()->id, $number);

        abort_if($order === null, 404);

        return Inertia::render('Wholesale/Portal/OrderDetail', [
            ...$this->layout->props(['title' => __('Order :number', ['number' => $number]), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'order' => $order,
        ]);
    }

    /**
     * The order's lines go into the cart at today's prices; the cart lists what was skipped.
     */
    public function reorder(Request $request, string $number): RedirectResponse
    {
        $order = $this->orderRepository->findForMerchant((int) $request->user()->id, $number);

        abort_if($order === null, 404);

        $result = $this->reorder->reorder($request->attributes->get('merchant'), $order);

        return to_route('store.wholesale.cart')->with('wholesale_reorder', $result);
    }
}
