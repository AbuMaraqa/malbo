<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Repositories\Contracts\WholesalePriceRepositoryInterface;

class WholesalePriceRepository extends BaseEloquentRepository implements WholesalePriceRepositoryInterface
{
    public function __construct(TierPrice $model)
    {
        parent::__construct($model);
    }

    public function tierPrices(int $tierId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return $this->query()->toBase()
            ->where('tier_id', $tierId)
            ->whereIn('product_id', $productIds)
            ->pluck('price', 'product_id')
            ->mapWithKeys(fn ($price, $productId) => [(int) $productId => (float) $price])
            ->all();
    }

    public function breaks(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $breaks = [];

        foreach ($this->breakQuery()->toBase()->whereIn('product_id', $productIds)->orderBy('min_quantity')->get(['product_id', 'min_quantity', 'discount_percent']) as $row) {
            $breaks[(int) $row->product_id][] = [(int) $row->min_quantity, (float) $row->discount_percent];
        }

        return $breaks;
    }

    public function allTierPrices(array $productIds): Collection
    {
        return $productIds === [] ? new Collection : $this->query()->whereIn('product_id', $productIds)->get();
    }

    public function breaksOf(int $productId): Collection
    {
        return $this->breakQuery()->where('product_id', $productId)->orderBy('min_quantity')->get();
    }

    public function breakQuery(): Builder
    {
        return (new QuantityBreak)->newQuery();
    }
}
