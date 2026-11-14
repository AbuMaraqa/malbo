<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Items\Entities\Product;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A quantity break of a product (a parent, or a product without variants): from `min_quantity`
 * pieces of it in one order, across its variants, its wholesale prices drop by `discount_percent`.
 * The highest break reached applies, on top of the tier price.
 */
class QuantityBreak extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'quantity_breaks');
    }

    protected $fillable = [
        'product_id',
        'min_quantity',
        'discount_percent',
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'discount_percent' => 'decimal:2',
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
}
