<?php

namespace Modules\Store\Repositories\Contracts;

use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\WishlistItem;

interface WishlistRepositoryInterface extends RepositoryInterface
{
    /**
     * The products on the user's wishlist, the newest first.
     *
     * @return list<int>
     */
    public function productIds(int $userId): array;

    public function findItem(int $userId, int $productId): ?WishlistItem;
}
