<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Services\StorefrontContact;
use Modules\Store\Services\StorefrontHome;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Support\ProductCard;
use Modules\Store\Support\Storefront;
use Modules\Store\Support\StorefrontLayout;

/**
 * The store's home page: hero, promises, categories, the signature piece and the store's picks,
 * story, values and the members circle. A section without content is not rendered.
 */
class HomeController extends Controller
{
    public const FEATURED = 8;

    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly StorefrontContact $contact,
        private readonly StorefrontHome $home,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(): Response
    {
        $appearance = app(StoreAppearanceSettings::class);
        $name = $this->home->storeName();
        $featured = $this->catalog->featured(self::FEATURED);
        // The first pick is shown large ("signature piece"), the rest in the grid.
        $signature = $featured->first();
        $picks = $featured->slice(1)->values();
        $story = $this->home->story();

        return Inertia::render('Store/Storefront/Home', [
            ...$this->layout->props([
                'title' => $name,
                'description' => Storefront::t($appearance->hero_subtitle) ?? Storefront::t($appearance->story_text),
                'json_ld' => [$this->organization($name, $appearance)],
            ]),
            'hero' => $this->home->hero(),
            'promises' => array_map(fn (array $promise) => [
                ...$promise,
                'url' => $promise['page'] ? route('store.page', ['slug' => $promise['page']]) : null,
            ], $this->home->promises()),
            'categories' => array_map(fn (array $category) => [
                ...$category,
                'url' => route('store.category', ['slug' => $category['slug']]),
            ], $this->catalog->categoryList()),
            'signature' => $signature?->toArray(),
            'featured' => $picks->map(fn (ProductCard $card) => $card->toArray())->all(),
            // Desktop: another pick floats beside the hero card (the first has its own section).
            'heroPiece' => $picks->first(fn (ProductCard $card) => $card->image !== null)?->toArray(),
            'story' => $story ? [...$story, 'about' => $story['about'] ? route('store.page', ['slug' => $story['about']]) : null] : null,
            'values' => $this->home->values()->all(),
            'membership' => $this->home->membership(),
        ]);
    }

    /**
     * schema.org data for search engines: the store, its logo, phone and social profiles.
     *
     * @return array<string, mixed>
     */
    private function organization(string $name, StoreAppearanceSettings $appearance): array
    {
        $channels = $this->contact->channels();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $name,
            'url' => route('home'),
            'logo' => Storefront::image($appearance->logo_path),
            'image' => Storefront::image($appearance->hero_image_path),
            'telephone' => $channels['phone'] ?? (isset($channels['whatsapp']) ? '+'.$channels['whatsapp'] : null),
            'email' => $channels['email'] ?? null,
            'address' => $channels['address'] ?? null,
            'sameAs' => array_values($this->contact->socials()) ?: null,
        ], fn ($value) => $value !== null);
    }
}
