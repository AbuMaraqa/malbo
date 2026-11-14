<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Store\Services\AbandonedCartService;

/**
 * A reminder's "Back to my cart" link (signed, see the route): the cart comes back to this browser.
 * A cart of an account asks its owner to sign in first and is never opened for anyone else.
 */
class CartRestoreController extends Controller
{
    public function __construct(private readonly AbandonedCartService $abandonedCartService) {}

    public function __invoke(Request $request, string $cart): RedirectResponse
    {
        return match ($this->abandonedCartService->restore($request, $cart)) {
            AbandonedCartService::RESTORED => to_route('store.cart')->with('success', __('Your cart is back.')),
            AbandonedCartService::ORDERED => to_route('store.cart')->with('error', __('This cart was already ordered.')),
            AbandonedCartService::SIGN_IN => to_route('store.account.login', ['back' => $request->getRequestUri()]),
            AbandonedCartService::FORBIDDEN => abort(403),
            default => abort(404),
        };
    }
}
