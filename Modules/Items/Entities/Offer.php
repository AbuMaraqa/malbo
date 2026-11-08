<?php

namespace Modules\Items\Entities;

use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\OfferType;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Items\Database\Factories\AttributeFactory;

class Offer extends Model implements TranslatableContract , HasMedia
{
    use LogsActivity;
    use Translatable;
    use InteractsWithMedia;
    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'offers');
    }

    protected $fillable = [
        'id',
        'branch_id',
        'name',
        'description',
        'value',
        'type',
        'code',
        'min_purchase_amount',
        'usage_limit',
        'usage_limit_per_user',
        'start_date',
        'end_date',
        'is_active',
        'offerable_type',
        'offerable_id',
        'created_by',
    ];

    protected $casts = [
        'type' => OfferType::class,
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
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


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('offer')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    public function addImage($file)
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (
            !$file instanceof \Illuminate\Http\UploadedFile
        ) {
            return null;
        }

        $this->clearMediaCollection('offer');

        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            $media = $this
                ->addMedia($file)
                ->usingFileName($fileName)
                ->toMediaCollection('offer', 'offers');

            return $media;
        } catch (\Exception $e) {
            Log::error('Error uploading brand image: ' . $e->getMessage());
            return null;
        }
    }

    public function getImageAttribute()
    {
        return $this->getFirstMediaUrl('offer');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function offerable(): MorphTo
    {
        return $this->morphTo();
    }


    public function products(): MorphToMany
    {
        return $this->morphedByMany(
            Product::class,
            'offerable',
            config('items.table_prefix') . 'offerables',
            'offer_id',
            'offerable_id'
        );
    }
}
