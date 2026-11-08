<?php

namespace Modules\Items\Entities;

use Modules\Items\database\factories\AttributeFactory;
use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Items\Enums\AttributeType;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

// use Modules\Items\Database\Factories\AttributeFactory;

class Attribute extends Model implements TranslatableContract
{
    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }

    use HasFactory;
    use LogsActivity;
    use Translatable;

    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'attributes');
    }

    protected $fillable = [
        'slug',
        'type',
        'created_by',
    ];

    protected $casts = [
        'status' => AttributeType::class,
    ];

    public $translatedAttributes = [
        'name',
        'description',
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

    protected static function booted()
    {
        parent::booted();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attributeValues(): HasMany
    {
        // In the order staff gave them. PostgreSQL rejects an aggregate on an ordered query: use reorder() before count().
        return $this->hasMany(AttributeValue::class , 'attribute_id')->orderBy('sort_order')->orderBy('id');
    }
}
