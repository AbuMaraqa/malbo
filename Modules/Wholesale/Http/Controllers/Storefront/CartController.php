<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Http\Requests\CartQuantitiesRequest;
use Modules\Wholesale\Services\WholesaleCartService;
use Modules\Wholesale\Support\PortalNav;

/**
 * The wholesale cart (approved merchants only). A product's quantities are set as a whole from its
 * grid; the cart removes a product with all its sizes. A JSON request gets the cart back as JSON.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly WholesaleCartService $cartService,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response|JsonResponse
    {
        $cart = $this->read($request);

        if ($request->expectsJson()) {
            return response()->json($cart);
        }

        return Inertia::render('Wholesale/Portal/Cart', [
            ...$this->layout->props(['title' => __('Wholesale cart'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            ...$cart,
            'checkoutUrl' => route('store.wholesale.checkout'),
            // What a reorder just put in the cart and what it skipped (ReorderService), once.
            'reorder' => $request->session()->get('wholesale_reorder'),
        ]);
    }

    public function set(CartQuantitiesRequest $request, int $product): RedirectResponse
    {
        $this->cartService->set($this->merchant($request), $product, $request->quantities());

        return back()->with('success', __('The cart is updated.'));
    }

    public function remove(Request $request, int $product): RedirectResponse
    {
        $this->cartService->remove((int) $request->user()->id, $product);

        return back();
    }

    /**
     * @return array{groups: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function read(Request $request): array
    {
        $merchant = $this->merchant($request);
        $cart = $this->cartService->current((int) $merchant->user_id);

        return $cart ? $this->cartService->read($cart, $merchant) : ['groups' => [], 'summary' => $this->cartService->summary([], $merchant)];
    }

    private function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }
}
