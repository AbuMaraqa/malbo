<?php

namespace Modules\Branch\Entities;

use Modules\Branch\database\factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branch\Enums\BranchStatus;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

// use Modules\Items\Database\Factories\AttributeFactory;

class Branch extends Model implements TranslatableContract
{
    use HasFactory;

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    use LogsActivity;
    use softDeletes;
    use Translatable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('branch.table_prefix') . 'branches');
    }

    protected $fillable = [
        'phone',
        'email',
        'city_id',
        'country_id',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'status',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'status' => BranchStatus::class,
    ];

    public $translatedAttributes = [
        'name',
        'description',
        'address',
    ];

    public $useTranslationFallback = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($branch) {
            if (! $branch->created_by) {
                $branch->created_by = auth()->id() ?? 1; // 1 كقيمة افتراضية إذا لم يكن هناك مستخدم مسجل
            }
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function workingHours()
    {
        return $this->hasMany(BranchWorkingHour::class);
    }
}
