<?php

namespace Modules\Store\Support;

use App\Support\Locales;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\StorefrontContact;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;

/**
 * What every storefront page's layout shows (the store's name, logo, announcement, contact details,
 * social links, open branches, footer pages) and what its <head> tells search engines. Sent as the
 * `store` and `seo` props; they are closures, so a partial reload of one prop does not build them.
 */
class StorefrontLayout
{
    private ?array $store = null;

    public function __construct(
        private StorefrontContact $contact,
        private PageRepositoryInterface $pages,
    ) {}

    /**
     * @param  array{title?: ?string, description?: ?string, noindex?: bool, og_type?: string, og_image?: ?string, json_ld?: list<array<string, mixed>>}  $meta
     * @return array{store: \Closure, seo: \Closure}
     */
    public function props(array $meta = []): array
    {
        return [
            'store' => fn () => $this->store(),
            'seo' => fn () => $this->seo($meta),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function store(): array
    {
        return $this->store ??= $this->buildStore();
    }

    /**
     * @param  array{title?: ?string, description?: ?string, noindex?: bool, og_type?: string, og_image?: ?string, json_ld?: list<array<string, mixed>>}  $meta
     * @return array<string, mixed>
     */
    public function seo(array $meta): array
    {
        $store = $this->store();
        $title = $meta['title'] ?? null;
        $fullTitle = filled($title) && $title !== $store['name'] ? $title.' — '.$store['name'] : $store['name'];
        $description = filled($meta['description'] ?? null) ? Str::limit(trim(strip_tags($meta['description'])), 160) : null;
        $image = $meta['og_image'] ?? $store['share_image'];
        $noindex = $meta['noindex'] ?? false;
        $canonical = url()->current();
        $alternates = $noindex ? [] : collect(Locales::keys())->mapWithKeys(fn (string $code) => [$code => Storefront::localeUrl($code)])->all();

        return [
            'title' => $fullTitle,
            'description' => $description,
            'noindex' => $noindex,
            'canonical' => $noindex ? null : $canonical,
            'alternates' => $alternates,
            'x_default' => $alternates[Locales::default()] ?? null,
            'og' => [
                'site_name' => $store['name'],
                'title' => $fullTitle,
                'type' => $meta['og_type'] ?? 'website',
                'url' => $canonical,
                'locale' => Storefront::rtl() ? 'ar_AR' : 'en_US',
                'description' => $description,
                'image' => $image,
            ],
            'twitter_card' => $image ? 'summary_large_image' : 'summary',
            // Ready for a <script> tag: `<` and `&` are escaped, so a store's text cannot close it.
            'json_ld' => array_map(
                fn (array $schema) => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
                $meta['json_ld'] ?? [],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStore(): array
    {
        $appearance = app(StoreAppearanceSettings::class);
        $locale = app()->getLocale();
        $otherLocale = collect(Locales::keys())->first(fn (string $code) => $code !== $locale) ?? $locale;
        $logo = Storefront::image($appearance->logo_path);
        $aboutSlug = $this->pages->publishedKeys()['about'] ?? null;
        $hasStory = Storefront::t($appearance->story_text) !== null;
        [$policyPages, $storePages] = $this->pages->footerPages()->partition(fn (Page $page) => in_array($page->key, Page::POLICIES, true));

        return [
            'name' => (string) app(GeneralSettings::class)->site_name,
            'logo' => $logo,
            'share_image' => Storefront::image($appearance->hero_image_path) ?? $logo,
            'announcement' => Storefront::t($appearance->announcement),
            // "About us": its page when published, else the story on the home page.
            'about_url' => $aboutSlug ? route('store.page', ['slug' => $aboutSlug]) : ($hasStory ? route('home').'#story' : null),
            'tagline' => Storefront::t($appearance->hero_subtitle),
            'other_locale' => $otherLocale,
            'other_locale_name' => config("app.locales.{$otherLocale}.native"),
            'other_locale_url' => Storefront::localeUrl($otherLocale, withQuery: true),
            'contact' => $this->contact->channels() ?: null,
            'whatsapp' => app(StoreContactSettings::class)->whatsapp,
            'socials' => $this->socials(),
            'branches' => $this->branches(),
            'footer_pages' => $storePages->map(self::pageLink(...))->values()->all(),
            'policy_pages' => $policyPages->map(self::pageLink(...))->values()->all(),
            'year' => (int) now()->year,
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function socials(): array
    {
        return collect($this->contact->socials())->map(fn (string $url, string $label) => ['label' => $label, 'url' => $url])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function branches(): array
    {
        return $this->contact->branches()->map(fn (Branch $branch) => [
            'id' => $branch->id,
            'name' => (string) $branch->name,
            'address' => $branch->address ?: null,
            'phone' => $branch->phone ?: null,
            'hours' => StorefrontContact::hours($branch->opening_time, $branch->closing_time),
            'map_url' => $branch->latitude && $branch->longitude
                ? 'https://www.google.com/maps/search/?api=1&query='.$branch->latitude.','.$branch->longitude
                : null,
        ])->values()->all();
    }

    /**
     * @return array{slug: string, key: ?string, title: string, url: string}
     */
    public static function pageLink(Page $page): array
    {
        return [
            'slug' => $page->slug,
            'key' => $page->key,
            'title' => (string) $page->title,
            'url' => route('store.page', ['slug' => $page->slug]),
        ];
    }
}
