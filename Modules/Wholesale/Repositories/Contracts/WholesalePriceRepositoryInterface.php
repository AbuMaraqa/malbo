<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\TierPrice;

/**
 * Tier prices (the model) and quantity breaks of products.
 */
interface WholesalePriceRepositoryInterface extends RepositoryInterface
{
    /**
     * These items' own prices in one tier.
     *
     * @param  list<int>  $productIds
     * @return array<int, float> product id => price
     */
    public function tierPrices(int $tierId, array $productIds): array;

    /**
     * These products' quantity breaks.
     *
     * @param  list<int>  $productIds
     * @return array<int, list<array{0: int, 1: float}>> product id => [min quantity, percent]
     */
    public function breaks(array $productIds): array;

    /**
     * Every tier price of these items, for the admin's form.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, TierPrice>
     */
    public function allTierPrices(array $productIds): Collection;

    /**
     * A product's quantity breaks, lowest first, for the admin's form.
     *
     * @return Collection<int, QuantityBreak>
     */
    public function breaksOf(int $productId): Collection;

    /**
     * A query over the quantity breaks (the service replaces them).
     */
    public function breakQuery(): Builder;
}
