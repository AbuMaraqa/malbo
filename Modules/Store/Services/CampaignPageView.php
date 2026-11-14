<?php

namespace Modules\Store\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\RichText;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Store\Contracts\InstagramFeedSource;
use Modules\Store\Entities\CampaignBlock;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Enums\CampaignBlockType;
use Modules\Store\Support\ProductCard;

/**
 * A campaign page as the storefront shows it: each block's texts in the current language (another
 * language's where this one has none), its picture, its products as cards (only what the store
 * shows, picked ones in their order) and its Instagram posts. A block with nothing to show (a
 * countdown that ended, a grid without products) is left out.
 */
class CampaignPageView
{
    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly CategoryRepositoryInterface $categories,
        private readonly InstagramFeedSource $instagram,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function blocks(CampaignPage $page): array
    {
        return array_values(array_filter($page->blocks->map(fn (CampaignBlock $block) => $this->block($block))->all()));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function block(CampaignBlock $block): ?array
    {
        $type = CampaignBlockType::tryFrom((string) $block->type);
        $text = fn (string $field) => $this->text($block, $field);
        $settings = (array) $block->settings;
        $base = ['id' => (int) $block->id, 'type' => $block->type];

        return match ($type) {
            CampaignBlockType::TEXT => ($html = RichText::make($text('html'))->toHtml()) === '' ? null : [...$base, 'html' => $html],
            CampaignBlockType::IMAGE => ($image = $block->getFirstMediaUrl('image')) === '' ? null : [...$base, 'image' => $image, 'alt' => $text('alt'), 'caption' => $text('caption') ?: null, 'link' => $settings['link'] ?? null],
            CampaignBlockType::BANNER => ($image = $block->getFirstMediaUrl('image')) === '' ? null : [
                ...$base, 'image' => $image, 'heading' => $text('heading') ?: null, 'text' => $text('text') ?: null,
                'button_label' => $text('button_label') ?: null, 'link' => $settings['link'] ?? null,
            ],
            CampaignBlockType::CTA => [...$base, 'heading' => $text('heading') ?: null, 'text' => $text('text') ?: null, 'button_label' => $text('button_label'), 'link' => $settings['link'] ?? null],
            CampaignBlockType::COUNTDOWN => $this->countdown($base, $settings, $text('heading'), $text('text')),
            CampaignBlockType::PRODUCTS => $this->products($base, $settings, $text('heading')),
            CampaignBlockType::INSTAGRAM => ($posts = $this->instagram->posts((int) ($settings['limit'] ?? 8))) === [] ? null : [...$base, 'heading' => $text('heading') ?: __('Follow us on Instagram'), 'posts' => $posts],
            null => null,
        };
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function countdown(array $base, array $settings, string $heading, string $text): ?array
    {
        $end = filled($settings['ends_at'] ?? null) ? Carbon::parse((string) $settings['ends_at']) : null;
        $left = $end ? (int) Carbon::now()->diffInSeconds($end, false) : 0;

        return $left <= 0 ? null : [...$base, 'heading' => $heading ?: null, 'text' => $text ?: null, 'ends_at' => $end->toIso8601String(), 'seconds_left' => $left];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function products(array $base, array $settings, string $heading): ?array
    {
        $limit = max(1, min(24, (int) ($settings['limit'] ?? 8)));
        $moreUrl = null;

        if (($settings['source'] ?? 'picked') === 'category') {
            $category = $this->categories->query()->find((int) ($settings['category_id'] ?? 0));
            $category = $category ? $this->catalog->category((string) $category->slug) : null;

            if ($category === null) {
                return null;
            }

            $cards = collect($this->catalog->listing(['category' => $category->slug, 'category_ids' => $this->catalog->categoryIds($category), 'sort' => 'newest'])->items())->take($limit);
            $moreUrl = route('store.category', ['slug' => $category->slug]);
        } else {
            $cards = $this->catalog->byIds(array_map('intval', (array) ($settings['product_ids'] ?? [])))->take($limit);
        }

        return $cards->isEmpty() ? null : [
            ...$base,
            'heading' => $heading ?: null,
            'cards' => $cards->map(fn (ProductCard $card) => $card->toArray())->values()->all(),
            'more_url' => $moreUrl,
        ];
    }

    /**
     * A text in the current language, else in another one that has it.
     */
    private function text(CampaignBlock $block, string $field): string
    {
        $locale = app()->getLocale();
        $own = $block->text($field, $locale);

        if ($own !== '') {
            return $own;
        }

        foreach (array_keys((array) $block->content) as $other) {
            if (($value = $block->text($field, (string) $other)) !== '') {
                return $value;
            }
        }

        return '';
    }
}
