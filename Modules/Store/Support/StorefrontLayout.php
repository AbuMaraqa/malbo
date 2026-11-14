<?php

namespace Modules\Store\Support;

use App\Support\Locales;
use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Enums\UserRole;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Repositories\Contracts\WishlistRepositoryInterface;
use Modules\Store\Services\CartService;
use Modules\Store\Services\Marketing\MetaEvents;
use Modules\Store\Services\StorefrontCatalog;
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
        private StorefrontCatalog $catalog,
        private CartService $cart,
        private WishlistRepositoryInterface $wishlist,
        private MetaEvents $metaEvents,
    ) {}

    /**
     * `marketing` is the Meta Pixel's prop (MetaEvents::props): $events builds the page's own events
     * (ViewContent...), only when the prop is sent, so a partial reload of another prop builds none.
     *
     * @param  array{title?: ?string, description?: ?string, noindex?: bool, og_type?: string, og_image?: ?string, json_ld?: list<array<string, mixed>>}  $meta
     * @param  (Closure(): list<array<string, mixed>|null>)|null  $events
     * @return array{store: Closure, seo: Closure, marketing: Closure}
     */
    public function props(array $meta = [], ?Closure $events = null): array
    {
        return [
            'store' => fn () => $this->store(),
            'seo' => fn () => $this->seo($meta),
            'marketing' => fn () => $this->metaEvents->props(request(), $events),
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
            // The header's category menu and the footer's shop column: active root categories.
            'categories' => array_map(fn (array $category) => [
                'id' => $category['id'],
                'name' => $category['name'],
                'image' => $category['image'],
                'url' => route('store.category', ['slug' => $category['slug']]),
            ], $this->catalog->categoryList()),
            // The categories page, linked from the menu and the footer once there is one to show.
            'categories_url' => $this->catalog->categoryList() !== [] ? route('store.categories') : null,
            // The footer's invitation to the members circle, while the store takes members.
            'members_url' => $appearance->membership_enabled ? route('store.members') : null,
            // The header's cart button: pieces in this browser's cart.
            'cart_count' => $this->cart->count(request()),
            'cart_url' => route('store.cart'),
            // The header's account menu, or its sign-in link.
            'account' => $this->account(),
            // What the hearts on product cards show: the products on this account's wishlist.
            'wishlist' => ($user = request()->user()) ? $this->wishlist->productIds((int) $user->id) : [],
            // The way into the wholesale portal from the store's header, footer and home.
            'wholesale' => $this->wholesale(),
            'year' => (int) now()->year,
        ];
    }

    /**
     * Who is signed in (a buyer or a staff member) and where the account's pages are.
     *
     * @return array{signed_in: bool, name: ?string, login_url: string, register_url: string, account_url: string, orders_url: string, wishlist_url: string, logout_url: string}
     */
    public function account(): array
    {
        $user = request()->user();

        return [
            'signed_in' => $user !== null,
            'name' => $user?->name,
            'login_url' => route('store.account.login'),
            'register_url' => route('store.account.register'),
            'account_url' => route('store.account'),
            'orders_url' => route('store.account.orders'),
            'wishlist_url' => route('store.account.wishlist'),
            'logout_url' => route('store.account.logout'),
        ];
    }

    /**
     * The wholesale portal's links, or null while no module serves it. Store does not depend on
     * the Wholesale module: it only links to its pages when their routes exist. A merchant (any
     * status) goes to the portal, whose account page says where the application stands; anyone
     * else is invited to apply. Never a price.
     *
     * @return array{merchant: bool, portal_url: string, apply_url: string}|null
     */
    public function wholesale(): ?array
    {
        if (! Route::has('store.wholesale.apply') || ! Route::has('store.wholesale.catalog')) {
            return null;
        }

        return [
            'merchant' => (bool) request()->user()?->hasRole(UserRole::MERCHANT->value),
            'portal_url' => route('store.wholesale.catalog'),
            'apply_url' => route('store.wholesale.apply'),
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
