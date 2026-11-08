<?php

namespace Modules\Items\Entities;

use Modules\Items\database\factories\LabelFactory;
use Astrotomic\Translatable\Translatable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Enums\AttributeType;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

// use Modules\Items\Database\Factories\AttributeFactory;

class Label extends Model implements TranslatableContract
{
    protected static function newFactory(): LabelFactory
    {
        return LabelFactory::new();
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

        $this->setTable(config('items.table_prefix') . 'labels');
    }

    protected $fillable = [
        'slug',
        'text_color',
        'background_color',
        'icon',
        'created_by',
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

        static::creating(function ($model) {
            // تعيين SKU قبل الحفظ
            if (empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug();
            }
        });
    }

    public function generateUniqueSlug(){
        $slug = Str::slug($this->name);
        $count = static::where('slug', 'like', $slug.'%')->count();
        return $count ? $slug.'-'.$count : $slug;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
