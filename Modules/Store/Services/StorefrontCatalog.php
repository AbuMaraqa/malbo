<?php

namespace Modules\Store\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Support\ProductCard;

/**
 * The storefront's catalog reads, as product cards with their storefront prices.
 *
 * What every visitor of a page reads the same way (the home picks, the category list, the first
 * page of a shop or category grid) comes from the store's cache. The cache holds a card's facts
 * and its price inputs, never a price: a discount window opens and closes on its own, so prices are
 * computed on every read.
 */
class StorefrontCatalog
{
    /** The columns PricingService reads. */
    private const PRICE_COLUMNS = ['base_price', 'sale_price', 'discount_type', 'discount_value', 'discount_start_date', 'discount_end_date'];

    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private PricingService $pricing,
        private StorefrontCatalogCache $cache,
    ) {}

    /**
     * @return Collection<int, ProductCard>
     */
    public function featured(int $limit = 8): Collection
    {
        $rows = $this->cache->remember('featured.'.app()->getLocale().'.'.$limit, fn () => $this->rows($this->products->featuredForStorefront($limit)));

        return $this->cards($rows);
    }

    /**
     * The shop grid page; its items are ProductCards.
     *
     * @param  array{category?: ?string, q?: ?string, sort?: ?string}  $filters
     */
    public function listing(array $filters): LengthAwarePaginator
    {
        if (blank($filters['q'] ?? null) && Paginator::resolveCurrentPage() === 1) {
            return $this->firstPage($filters);
        }

        $page = $this->products->storefrontListing($filters);
        $page->setCollection($this->cards($this->rows($page->getCollection())));

        return $page;
    }

    /**
     * Active root categories in their sort order, as the home tiles and shop chips show them.
     *
     * @return list<array{id: int, slug: string, name: string, image: ?string}>
     */
    public function categoryList(): array
    {
        return $this->cache->remember('categories.'.app()->getLocale(), fn () => $this->categories()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => (string) ($category->name ?: $category->translations->first()?->name),
                'image' => $category->getFirstMediaUrl('category') ?: null,
            ])
            ->values()
            ->all());
    }

    /**
     * Active root categories in their sort order.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return $this->categories->tree()->loadMissing('media');
    }

    public function category(string $slug): ?Category
    {
        return $this->categories->findActiveBySlug($slug);
    }

    public function product(string $slug): ?Product
    {
        return $this->products->findPublishedBySlug($slug);
    }

    /**
     * @return Collection<int, ProductCard>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        return $this->cards($this->rows($this->products->relatedForStorefront($product, $limit)));
    }

    /**
     * @param  array{category?: ?string, q?: ?string, sort?: ?string}  $filters
     */
    private function firstPage(array $filters): LengthAwarePaginator
    {
        $key = implode('.', ['listing', app()->getLocale(), $filters['category'] ?? 'all', $filters['sort'] ?? 'newest']);

        $cached = $this->cache->remember($key, function () use ($filters) {
            $page = $this->products->storefrontListing($filters);

            return ['rows' => $this->rows($page->getCollection()), 'total' => $page->total(), 'per_page' => $page->perPage()];
        });

        return new Paginator($this->cards($cached['rows']), $cached['total'], $cached['per_page'], 1, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    /**
     * What a card needs, in a form the cache can hold.
     *
     * @param  iterable<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $products): array
    {
        return collect($products)
            ->map(fn (Product $product) => [
                ...ProductCard::facts($product),
                'pricing' => array_intersect_key($product->getAttributes(), array_flip(self::PRICE_COLUMNS)),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, ProductCard>
     */
    private function cards(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row) => ProductCard::fromFacts($row, $this->pricing->for((new Product)->setRawAttributes($row['pricing']))));
    }
}
