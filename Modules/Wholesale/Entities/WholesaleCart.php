<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;

/**
 * A merchant's wholesale cart, never mixed with the storefront's. Open while `order_id` is null
 * (one per user); checkout fills `order_id` and `converted_at`. Holds no prices.
 */
class WholesaleCart extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'carts');
    }

    protected $fillable = [
        'user_id',
        'order_id',
        'last_activity_at',
        'converted_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleCartItem::class, 'cart_id')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
