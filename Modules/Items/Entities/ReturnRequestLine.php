<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units of one order line sent back. For an exchange, `exchange_product_id` is the product given
 * instead, one for one.
 */
class ReturnRequestLine extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'return_request_lines');
    }

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'exchange_product_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function exchangeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'exchange_product_id')->withTrashed();
    }
}
