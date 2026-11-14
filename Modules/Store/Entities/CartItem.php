<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Items\Entities\Product;

/**
 * A line of a cart: a sellable product (a variant, or a product without variants) and how many.
 *
 * A set or bundle's line also holds what was chosen for each component (`components`: a list of
 * component_id and product_id); the same bundle with other choices is another line (`choice_key`,
 * empty for any other product).
 */
class CartItem extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'cart_items');
    }

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'choice_key',
        'components',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'components' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
