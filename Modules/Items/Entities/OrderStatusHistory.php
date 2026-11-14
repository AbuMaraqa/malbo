<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Enums\OrderStatus;

/**
 * A status an order reached, and when. Written by OrderService only; the buyer's tracking page
 * shows them as a timeline.
 */
class OrderStatusHistory extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'order_status_history');
    }

    protected $fillable = [
        'order_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
