<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartItem;
use Modules\Store\Http\Requests\CartItemRequest;
use Modules\Store\Services\CartService;
use Modules\Store\Services\Marketing\MetaEvents;
use Modules\Store\Support\Price;
use Modules\Store\Support\StorefrontLayout;

/**
 * The cart page and its changes. The cart is this browser's (its cookie); a line of another cart is
 * a 404. Every price comes from the server on each visit.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly StorefrontLayout $layout,
        private readonly MetaEvents $events,
    ) {}

    public function show(Request $request): Response
    {
        $cart = $this->cart->current($request);
        $lines = $cart ? $this->cart->lines($cart) : [];
        $subtotal = CartService::subtotal($lines);
        $blocked = collect($lines)->contains(fn (array $line) => ! $line['available']);

        return Inertia::render('Store/Storefront/Cart', [
            ...$this->layout->props(['title' => __('Your cart'), 'noindex' => true]),
            'lines' => $lines,
            'summary' => [
                'subtotal' => $subtotal,
                'subtotal_label' => (new Price($subtotal))->format(),
                'count' => array_sum(array_column($lines, 'quantity')),
                'can_checkout' => $lines !== [] && ! $blocked,
                'checkout_url' => route('store.checkout'),
            ],
        ]);
    }

    public function add(CartItemRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->cart->add($request, (int) $data['product_id'], (int) $data['quantity'], (array) ($data['components'] ?? []));
        $this->events->addToCart($request, (int) $data['product_id'], (int) $data['quantity']);

        return back()->with('success', __('Added to your cart.'));
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        [$cart, $line] = $this->line($request, $item);
        $quantity = (int) $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']])['quantity'];

        $this->cart->setQuantity($cart, $line, $quantity);

        return back();
    }

    public function remove(Request $request, int $item): RedirectResponse
    {
        [$cart, $line] = $this->line($request, $item);

        $this->cart->remove($cart, $line);

        return back();
    }

    /**
     * @return array{0: Cart, 1: CartItem}
     */
    private function line(Request $request, int $item): array
    {
        $cart = $this->cart->current($request);
        $line = $cart?->items->firstWhere('id', $item);

        abort_if($line === null, 404);

        return [$cart, $line];
    }
}
