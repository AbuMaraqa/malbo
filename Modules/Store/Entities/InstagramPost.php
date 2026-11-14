<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A post of the store's Instagram, as the storefront shows it: its picture (`image`), its link on
 * Instagram, its caption and the products it shows ("shop the look"). `source` is `manual` for a
 * post entered in the admin; a sync from the Graph API writes its own rows (`external_id`).
 */
class InstagramPost extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;

    public const MANUAL = 'manual';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'instagram_posts');
    }

    protected $fillable = [
        'permalink',
        'caption',
        'posted_at',
        'sort_order',
        'is_active',
        'source',
        'external_id',
        'created_by',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'source' => self::MANUAL,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The products the post shows, in their order.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, config('store.table_prefix').'instagram_post_product', 'instagram_post_id', 'product_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
