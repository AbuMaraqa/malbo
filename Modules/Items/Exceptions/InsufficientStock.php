<?php

namespace Modules\Items\Exceptions;

use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Product;

/**
 * A sale asked for more than the stock holds. A validation error on `items`, so the form or the API
 * shows it like any other.
 */
class InsufficientStock extends ValidationException
{
    public static function for(Product $product, int $left): self
    {
        return self::withMessages(['items' => __('Only :count left of :name.', [
            'count' => max(0, $left),
            'name' => $product->name ?? $product->sku ?? '#'.$product->id,
        ])]);
    }
}
