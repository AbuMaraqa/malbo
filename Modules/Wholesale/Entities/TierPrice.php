<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Items\Entities\Product;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A sellable item's own wholesale price in one tier (a variant, or a product without variants). It
 * replaces the tier's discount off the wholesale price; quantity breaks still apply on top.
 */
class TierPrice extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'tier_prices');
    }

    protected $fillable = [
        'product_id',
        'tier_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }
}
