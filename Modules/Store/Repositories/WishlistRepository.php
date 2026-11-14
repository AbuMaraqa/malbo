<?php

namespace Modules\Store\Repositories;

use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\WishlistItem;
use Modules\Store\Repositories\Contracts\WishlistRepositoryInterface;

class WishlistRepository extends BaseEloquentRepository implements WishlistRepositoryInterface
{
    public function __construct(WishlistItem $model)
    {
        parent::__construct($model);
    }

    public function productIds(int $userId): array
    {
        return $this->query()->where('user_id', $userId)->orderByDesc('id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    public function findItem(int $userId, int $productId): ?WishlistItem
    {
        /** @var WishlistItem|null */
        return $this->query()->where('user_id', $userId)->where('product_id', $productId)->first();
    }
}
