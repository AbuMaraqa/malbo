<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A block of a campaign page (Modules\Store\Enums\CampaignBlockType). `settings` holds what does not
 * depend on the language (a link, products, a category, an end date); `content` its texts per
 * language: {ar: {heading, text, ...}, en: {...}}. An image or banner block has its picture (`image`).
 */
class CampaignBlock extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'campaign_blocks');
    }

    protected $fillable = [
        'campaign_page_id',
        'type',
        'sort_order',
        'settings',
        'content',
    ];

    protected $casts = [
        'settings' => 'array',
        'content' => 'array',
        'sort_order' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CampaignPage::class, 'campaign_page_id');
    }

    /**
     * One of its texts in $locale; empty when that language has none.
     */
    public function text(string $field, string $locale): string
    {
        return trim((string) ($this->content[$locale][$field] ?? ''));
    }
}
