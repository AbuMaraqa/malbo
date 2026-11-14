<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Store\Http\Requests\CartContactRequest;
use Modules\Store\Services\AbandonedCartService;
use Modules\Store\Services\CartService;

/**
 * Checkout's "Remind me about my cart": the contact the buyer typed, kept on this browser's cart
 * only while the box is ticked (AbandonedCartService::saveContact). JSON, no page change.
 */
class CartContactController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly AbandonedCartService $abandoned,
    ) {}

    public function __invoke(CartContactRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cart = $this->cart->current($request);

        if ($cart === null) {
            return response()->json(['saved' => false]);
        }

        $this->abandoned->saveContact($cart, (bool) $data['remind'], $data['email'] ?? null, $data['phone'] ?? null, app()->getLocale());

        return response()->json(['saved' => true]);
    }
}
