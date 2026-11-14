<?php

namespace Modules\Wholesale\Services;

use Modules\Items\Entities\Product;
use Modules\Store\Services\PricingService;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesalePriceRepositoryInterface;
use Modules\Wholesale\Support\PriceBook;

/**
 * The one place wholesale prices come from. `tierFor()` is the merchant's tier (its own, else the
 * default tier, else none: no discount); `book()` loads that tier's overrides and the products'
 * quantity breaks into a PriceBook, which holds the rules. The suggested retail price shown next to
 * a wholesale price is the storefront's price (PricingService).
 */
class WholesalePricing
{
    public function __construct(
        private readonly WholesalePriceRepositoryInterface $prices,
        private readonly TierRepositoryInterface $tiers,
        private readonly PricingService $retail,
    ) {}

    public function tierFor(?Merchant $merchant): ?Tier
    {
        if ($merchant?->tier_id) {
            $merchant->loadMissing('tier.translations');

            if ($merchant->tier) {
                return $merchant->tier;
            }
        }

        return $this->tiers->default();
    }

    /**
     * The prices of one tier for these products: the items (variants, products without variants)
     * whose overrides count, and their families' parents, whose breaks count. Pass every product id
     * involved; each one's group id is added.
     *
     * @param  iterable<Product>  $items
     */
    public function book(?Tier $tier, iterable $items): PriceBook
    {
        $ids = [];
        $groups = [];

        foreach ($items as $item) {
            $ids[] = (int) $item->id;
            $groups[] = PriceBook::groupId($item);
        }

        $ids = array_values(array_unique($ids));
        $groups = array_values(array_unique($groups));

        return new PriceBook(
            $tier ? (float) $tier->discount_percent : null,
            $tier ? $this->prices->tierPrices((int) $tier->id, $ids) : [],
            $this->prices->breaks($groups),
        );
    }

    /**
     * What the storefront sells the item for: the price a merchant is told to sell at.
     */
    public function suggestedRetail(Product $item): Price
    {
        return $this->retail->for($item);
    }
}
