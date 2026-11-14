<?php

namespace Modules\Store\Entities;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Entities\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A campaign page: blocks in order (CampaignBlock) under its own slug. Public while `published` and
 * inside its dates (either may be empty); staff with `Campaign Page View List` see it any time, as a
 * preview. Title and SEO texts per language.
 */
class CampaignPage extends Model implements TranslatableContract
{
    use LogsActivity;
    use Translatable;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'campaign_pages');
    }

    protected $fillable = [
        'slug',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public $translatedAttributes = [
        'title',
        'seo_title',
        'seo_description',
    ];

    public $useTranslationFallback = true;

    protected $translationForeignKey = 'campaign_page_id';

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

    /**
     * Its blocks, in their order.
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(CampaignBlock::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Published and inside its dates now.
     */
    public function isLive(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return $this->status === self::PUBLISHED
            && ($this->starts_at === null || $this->starts_at->lte($at))
            && ($this->ends_at === null || $this->ends_at->gte($at));
    }
}
