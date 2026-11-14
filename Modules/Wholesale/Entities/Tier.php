<?php

namespace Modules\Wholesale\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A wholesale price tier (a merchant's level): a discount off the base wholesale price. Products
 * may have their own price per tier (TierPrice), which wins. One tier may be the default: merchants
 * without a tier get it.
 */
class Tier extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'tiers');
    }

    protected $fillable = [
        'discount_percent',
        'sort_order',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
    ];

    public $translatedAttributes = ['name'];

    public $useTranslationFallback = true;

    protected $translationForeignKey = 'tier_id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }
}
