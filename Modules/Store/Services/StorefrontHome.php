<?php

namespace Modules\Store\Services;

use Illuminate\Support\Collection;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Entities\HeroSlide;
use Modules\Store\Repositories\Contracts\HeroSlideRepositoryInterface;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Storefront;

/**
 * The home page's texts in the current locale, for the storefront and the app API: hero,
 * promises, story, values and the members circle. Pages are given by slug; the storefront
 * turns them into links. A section without content is null (or empty).
 */
class StorefrontHome
{
    private ?array $pageKeys = null;

    /** The most slides the home slider shows. */
    public const SLIDES = 10;

    public function __construct(
        private PageRepositoryInterface $pages,
        private HeroSlideRepositoryInterface $slides,
    ) {}

    public function storeName(): string
    {
        return (string) app(GeneralSettings::class)->site_name;
    }

    /**
     * @return array{eyebrow: ?string, title: string, subtitle: ?string, image: ?string}
     */
    public function hero(): array
    {
        $appearance = app(StoreAppearanceSettings::class);

        return [
            'eyebrow' => Storefront::t($appearance->hero_eyebrow),
            'title' => Storefront::t($appearance->hero_title) ?? $this->storeName(),
            'subtitle' => Storefront::t($appearance->hero_subtitle),
            'image' => Storefront::image($appearance->hero_image_path),
        ];
    }

    /**
     * The slides staff set up for the home slider, shown now and in their order, with their texts
     * in the current locale (another language's when this one has none). Empty: the storefront
     * builds its own slides from the hero texts and the store's picks.
     *
     * @return list<array{id: int, eyebrow: ?string, title: ?string, subtitle: ?string, button: ?string, url: ?string, image: string, mobile_image: ?string}>
     */
    public function slides(): array
    {
        return $this->slides->live(self::SLIDES)
            ->map(function (HeroSlide $slide): array {
                $texts = $this->texts($slide);

                return [
                    'id' => (int) $slide->id,
                    'eyebrow' => $texts['eyebrow'],
                    'title' => $texts['title'],
                    'subtitle' => $texts['subtitle'],
                    'button' => $texts['button_label'],
                    'url' => $slide->link,
                    'image' => $slide->getFirstMediaUrl('image'),
                    'mobile_image' => $slide->getFirstMediaUrl('mobile_image') ?: null,
                ];
            })
            ->filter(fn (array $slide) => $slide['image'] !== '')
            ->values()
            ->all();
    }

    /**
     * The promises under the hero, each backed by a published page or a WhatsApp number.
     *
     * @return list<array{key: string, title: string, text: string, page: ?string}>
     */
    public function promises(): array
    {
        $pages = $this->pageKeys();
        $whatsapp = (bool) app(StoreContactSettings::class)->whatsapp;

        return array_values(array_filter([
            isset($pages['shipping']) ? ['key' => 'shipping', 'title' => __('Careful delivery'), 'text' => __('Prepared with care and delivered to your door'), 'page' => $pages['shipping']] : null,
            isset($pages['returns']) ? ['key' => 'returns', 'title' => __('Easy returns'), 'text' => __('Exchange or return within the policy window'), 'page' => $pages['returns']] : null,
            $whatsapp ? ['key' => 'whatsapp', 'title' => __('Pay on delivery'), 'text' => __('Confirm by WhatsApp, pay when it arrives'), 'page' => null] : null,
            isset($pages['privacy']) ? ['key' => 'privacy', 'title' => __('Your data is safe'), 'text' => __('We never sell or share your details'), 'page' => $pages['privacy']] : null,
        ]));
    }

    /**
     * @return array{title: ?string, text: string, image: ?string, about: ?string}|null  about = the About page's slug
     */
    public function story(): ?array
    {
        $appearance = app(StoreAppearanceSettings::class);
        $text = Storefront::t($appearance->story_text);

        return $text === null ? null : [
            'title' => Storefront::t($appearance->story_title),
            'text' => $text,
            'image' => Storefront::image($appearance->story_image_path),
            'about' => $this->pageKeys()['about'] ?? null,
        ];
    }

    /**
     * @return Collection<int, array{title: string, text: ?string}>
     */
    public function values(): Collection
    {
        return Storefront::values(app(StoreAppearanceSettings::class)->values);
    }

    /**
     * @return array{title: string, text: string}|null  null while the store does not take members
     */
    public function membership(): ?array
    {
        $appearance = app(StoreAppearanceSettings::class);

        return $appearance->membership_enabled ? [
            'title' => Storefront::t($appearance->membership_title) ?? __('Join the :store circle', ['store' => $this->storeName()]),
            'text' => Storefront::t($appearance->membership_text) ?? __('Be the first to see new arrivals, and get offers we keep for our members only.'),
        ] : null;
    }

    /**
     * A slide's texts, all from one language so words never mix: the current locale's when it has
     * any, else the first language that has some. A slide with no texts at all is a picture alone.
     *
     * @return array{eyebrow: ?string, title: ?string, subtitle: ?string, button_label: ?string}
     */
    private function texts(HeroSlide $slide): array
    {
        $fields = ['eyebrow', 'title', 'subtitle', 'button_label'];
        $read = fn ($translation) => collect($fields)->mapWithKeys(fn (string $field) => [$field => filled($translation?->{$field}) ? trim((string) $translation->{$field}) : null])->all();
        $hasText = fn (array $texts) => array_filter($texts) !== [];

        $current = $read($slide->translations->firstWhere('locale', app()->getLocale()));

        return $hasText($current) ? $current : ($slide->translations->map($read)->first($hasText) ?? $current);
    }

    /**
     * @return array<string, string> key => slug of the published system pages
     */
    private function pageKeys(): array
    {
        return $this->pageKeys ??= $this->pages->publishedKeys();
    }
}
