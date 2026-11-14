<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;

/**
 * A buyer's cart, found by the token in their cookie. Open while `order_id` is null; checkout
 * fills `order_id` and `converted_at`. Holds no prices.
 */
class Cart extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'carts');
    }

    protected $fillable = [
        'uuid',
        'token',
        'user_id',
        'order_id',
        'last_activity_at',
        'converted_at',
        // The contact a buyer agreed (at checkout) to be reminded at, when, and the cart's language.
        'contact_email',
        'contact_phone',
        'contact_consent_at',
        'locale',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'converted_at' => 'datetime',
        'contact_consent_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /**
     * Abandoned-cart reminders sent (or tried) for this cart, by sequence.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(CartReminder::class)->orderBy('sequence');
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
