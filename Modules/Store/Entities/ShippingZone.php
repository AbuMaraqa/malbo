<?php

namespace Modules\Store\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A group of cities with one delivery fee, an expected delivery time (days) and, optionally, a
 * free-delivery threshold. A city belongs to at most one active zone; a city in no active zone
 * gets no delivery.
 */
class ShippingZone extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'shipping_zones');
    }

    protected $fillable = [
        'fee',
        'eta_min_days',
        'eta_max_days',
        'free_shipping_threshold',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'eta_min_days' => 'integer',
        'eta_max_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $translatedAttributes = ['name'];

    public $useTranslationFallback = true;

    protected $translationForeignKey = 'shipping_zone_id';

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

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, config('store.table_prefix').'shipping_zone_city', 'shipping_zone_id', 'city_id');
    }

    /**
     * "1–3 days", or "2 days" when both ends are the same.
     */
    public function etaLabel(): string
    {
        return $this->eta_min_days === $this->eta_max_days
            ? __(':count days', ['count' => $this->eta_max_days])
            : __(':min–:max days', ['min' => $this->eta_min_days, 'max' => $this->eta_max_days]);
    }
}
