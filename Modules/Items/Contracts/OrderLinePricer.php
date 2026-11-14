<?php

namespace Modules\Items\Contracts;

use Illuminate\Container\Attributes\Bind;
use Illuminate\Support\Collection;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Services\RetailOrderLinePricer;

/**
 * What a new or changed order line costs when staff edit an order (OrderService). The store's
 * prices by default (RetailOrderLinePricer); another module may bind its own, such as the wholesale
 * portal pricing its own orders at the merchant's prices. Items knows nothing of who does.
 */
#[Bind(RetailOrderLinePricer::class)]
interface OrderLinePricer
{
    /**
     * @param  Order|null  $order  the order being edited; null for a new order made in the admin
     * @param  Collection<int, Product>  $products  every product on the order's lines after the edit, by id (OrderService::SNAPSHOT_RELATIONS loaded)
     * @param  array<int, int>  $quantities  product id => pieces on the order's lines after the edit
     * @return array<int, float> product id => unit price, for every product in $products
     */
    public function prices(?Order $order, Collection $products, array $quantities): array;
}
