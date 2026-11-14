<?php

namespace Modules\Store\Enums;

/**
 * The ready blocks a campaign page is built from. Each keeps its own texts per language (`content`)
 * and settings:
 * - text: html (sanitized rich text);
 * - image: the picture, alt and caption, an optional link;
 * - banner: the picture, heading, text and button, a link;
 * - products: a heading; picked products (`product_ids`) or a category (`category_id`), at most `limit`;
 * - cta: heading, text and button, a link;
 * - countdown: heading and text, the end (`ends_at`); left out once it passed;
 * - instagram: a heading, at most `limit` posts (InstagramFeedSource).
 */
enum CampaignBlockType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case BANNER = 'banner';
    case PRODUCTS = 'products';
    case CTA = 'cta';
    case COUNTDOWN = 'countdown';
    case INSTAGRAM = 'instagram';

    /**
     * The texts it keeps per language.
     *
     * @return list<string>
     */
    public function texts(): array
    {
        return match ($this) {
            self::TEXT => ['html'],
            self::IMAGE => ['alt', 'caption'],
            self::BANNER, self::CTA => ['heading', 'text', 'button_label'],
            self::PRODUCTS, self::INSTAGRAM => ['heading'],
            self::COUNTDOWN => ['heading', 'text'],
        };
    }

    public function hasImage(): bool
    {
        return in_array($this, [self::IMAGE, self::BANNER], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('Text'),
            self::IMAGE => __('Image'),
            self::BANNER => __('Banner'),
            self::PRODUCTS => __('Products'),
            self::CTA => __('Call to action'),
            self::COUNTDOWN => __('Countdown'),
            self::INSTAGRAM => __('Instagram'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
