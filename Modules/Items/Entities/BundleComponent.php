<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of a set or bundle: a product (the buyer chooses its variant) or one variant, and how
 * many of it one bundle holds. `product_id` is empty once the product was deleted for good: the
 * bundle cannot be sold without it (BundleStock).
 */
class BundleComponent extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'bundle_components');
    }

    protected $fillable = [
        'bundle_id',
        'product_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
