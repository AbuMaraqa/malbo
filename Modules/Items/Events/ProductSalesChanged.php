<?php

namespace Modules\Items\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * SalesCounter moved the `sales_count` of these products (an empty list: a full recount). The
 * column is written without model events, so caches that sort by it (the storefront's "best
 * selling" page) listen to this instead.
 */
class ProductSalesChanged
{
    use Dispatchable;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public readonly array $productIds) {}
}
