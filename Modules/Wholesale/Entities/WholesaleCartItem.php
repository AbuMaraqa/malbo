<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Items\Entities\Product;

/**
 * A line of a wholesale cart: a sellable product (a variant, or a product without variants) and
 * how many.
 */
class WholesaleCartItem extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'cart_items');
    }

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WholesaleCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
