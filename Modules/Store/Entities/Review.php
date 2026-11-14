<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Store\Enums\ReviewStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A buyer's review of a product (the parent, whichever variant they bought): a rating from 1 to 5,
 * a title and a text (plain text, escaped wherever shown), up to four photos (re-encoded, without
 * their EXIF), the order that made the buyer eligible, the staff's status and reply. One per
 * product and account.
 */
class Review extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;

    public const MAX_PHOTOS = 4;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'reviews');
    }

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'title',
        'body',
        'status',
        'staff_reply',
        'replied_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => ReviewStatus::class,
        'replied_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['rating', 'status', 'staff_reply'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
