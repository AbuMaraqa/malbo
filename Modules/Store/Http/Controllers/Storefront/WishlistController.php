<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Repositories\Contracts\WishlistRepositoryInterface;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Services\WishlistService;
use Modules\Store\Support\ProductCard;
use Modules\Store\Support\StorefrontLayout;

/**
 * The heart on product cards and the product page, and the account's wishlist page. A guest's
 * heart is a link to the sign-in page, which brings them back (`?back=`).
 */
class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlist,
        private readonly WishlistRepositoryInterface $items,
        private readonly StorefrontCatalog $catalog,
        private readonly StorefrontLayout $layout,
    ) {}

    public function index(Request $request): Response
    {
        $ids = $this->items->productIds((int) $request->user()->id);

        return Inertia::render('Store/Storefront/Account/Wishlist', [
            ...$this->layout->props(['title' => __('My wishlist'), 'noindex' => true]),
            'cards' => fn () => $this->catalog->byIds($ids)->map(fn (ProductCard $card) => $card->toArray())->values()->all(),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $productId = (int) $request->validate(['product_id' => ['required', 'integer']])['product_id'];

        $added = $this->wishlist->toggle($request->user(), $productId);

        return back()->with('success', $added ? __('Added to your wishlist.') : __('Removed from your wishlist.'));
    }
}
