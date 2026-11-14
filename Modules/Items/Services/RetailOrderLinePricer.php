<?php

namespace Modules\Items\Services;

use Illuminate\Support\Collection;
use Modules\Items\Contracts\OrderLinePricer;
use Modules\Items\Entities\Order;
use Modules\Store\Services\PricingService;

/**
 * The default OrderLinePricer: each product's storefront price now (PricingService), whatever the
 * order and the quantities.
 */
class RetailOrderLinePricer implements OrderLinePricer
{
    public function __construct(private readonly PricingService $pricing) {}

    public function prices(?Order $order, Collection $products, array $quantities): array
    {
        return $products->mapWithKeys(fn ($product, $id) => [(int) $id => $this->pricing->for($product)->amount])->all();
    }
}
