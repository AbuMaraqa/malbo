<?php

namespace Modules\Store\Services;

use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Repositories\Contracts\WishlistRepositoryInterface;

/**
 * A buyer's wishlist: the products (a parent or a single product, never a variant) they keep for
 * later. Only a product the storefront shows (published and active) can be added; one that later
 * leaves the storefront stays on the list but is not shown.
 */
class WishlistService
{
    public function __construct(
        private readonly WishlistRepositoryInterface $items,
        private readonly ProductRepositoryInterface $products,
    ) {}

    /**
     * Adds the product, or removes it when it is already there.
     *
     * @return bool whether the product is on the list now
     *
     * @throws ValidationException on `product_id`
     */
    public function toggle(User $user, int $productId): bool
    {
        $item = $this->items->findItem((int) $user->id, $productId);

        if ($item !== null) {
            $this->items->delete($item);

            return false;
        }

        if (! $this->products->publishedForStorefront()->whereKey($productId)->exists()) {
            throw ValidationException::withMessages(['product_id' => __('This product is no longer available.')]);
        }

        // Two clicks at once add it once: (user_id, product_id) is unique.
        $this->items->query()->firstOrCreate(['user_id' => $user->id, 'product_id' => $productId]);

        return true;
    }
}
