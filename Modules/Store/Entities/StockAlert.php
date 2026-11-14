<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Items\Entities\Product;

/**
 * "Notify me when available": a sellable item (a variant, or a product without variants), the email
 * and/or phone to tell, in which language. Waiting while `notified_at` is empty; one waiting alert
 * per item and contact.
 */
class StockAlert extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'stock_alerts');
    }

    protected $fillable = [
        'product_id',
        'email',
        'phone',
        'locale',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
