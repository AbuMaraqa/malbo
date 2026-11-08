<?php

namespace Modules\Store\Entities;

use Modules\Store\database\factories\PageFactory;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * A store page: a policy, the FAQ, or a page the owner added. System pages (`key` set) are
 * created with every store and can be edited or hidden, not deleted.
 */
class Page extends Model implements TranslatableContract
{
    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }

    use HasFactory;
    use Translatable;
    use SoftDeletes;
    use LogsActivity;

    public const SYSTEM = ['about', 'shipping', 'returns', 'privacy', 'terms', 'faq'];

    /** System pages listed under "Policies" in the footer and the help menu; the rest are about the store. */
    public const POLICIES = ['shipping', 'returns', 'privacy', 'terms'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix') . 'pages');
    }

    protected $fillable = [
        'slug',
        'is_published',
        'show_in_footer',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'show_in_footer' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $translatedAttributes = [
        'title',
        'body',
        'meta_description',
    ];

    public $useTranslationFallback = true;

    /** The translations table keeps the column it was created with. */
    protected $translationForeignKey = 'store_page_id';

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
    }

    public function isSystem(): bool
    {
        return $this->key !== null;
    }
}
