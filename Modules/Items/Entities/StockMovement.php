<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Enums\StockMovementType;

/**
 * One change to a product's stock, written by InventoryService only. Never edited afterwards: the
 * ledger is the history of the stock.
 */
class StockMovement extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'stock_movements');
    }

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'balance_after',
        'order_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'type' => StockMovementType::class,
        'quantity' => 'integer',
        'balance_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
