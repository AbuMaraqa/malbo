<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;

/**
 * A product a buyer keeps for later. WishlistService writes it.
 */
class WishlistItem extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'wishlist_items');
    }

    protected $fillable = ['user_id', 'product_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
