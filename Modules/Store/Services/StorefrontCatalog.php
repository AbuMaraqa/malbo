<?php

namespace Modules\Store\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Repositories\Contracts\AttributeRepositoryInterface;
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
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private PricingService $pricing,
        private StorefrontCatalogCache $cache,
        private ProductBadges $badges,
        private AttributeRepositoryInterface $attributes,
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
     * The newest published products, as the home's "new arrivals" shows them: the start of the
     * shop's first page, which the store's cache already holds.
     *
     * @return Collection<int, ProductCard>
     */
    public function newArrivals(int $limit = 8): Collection
    {
        return collect($this->firstPage(['sort' => 'newest'])->items())->take($limit)->values();
    }

    /**
     * The best-selling published products (by sales, then newest): the start of the shop's first
     * page sorted that way, which the store's cache already holds.
     *
     * @return Collection<int, ProductCard>
     */
    public function bestSellers(int $limit = 8): Collection
    {
        return collect($this->firstPage(['sort' => 'best_selling'])->items())->take($limit)->values();
    }

    /**
     * The shop grid page; its items are ProductCards. The first page without search or filters is
     * cached (per category and sort): what most visitors open.
     *
     * @param  array{category?: ?string, q?: ?string, attributes?: array<int, list<int>>, price_min?: ?float, price_max?: ?float, sort?: ?string}  $filters
     */
    public function listing(array $filters): LengthAwarePaginator
    {
        if (self::unfiltered($filters) && Paginator::resolveCurrentPage() === 1) {
            return $this->firstPage($filters);
        }

        $page = $this->products->storefrontListing($filters);
        $page->setCollection($this->cards($this->rows($page->getCollection())));

        return $page;
    }

    /**
     * The shop's facets (ProductRepositoryInterface::storefrontFacets); cached for a page without
     * search or filters.
     *
     * @param  array<string, mixed>  $filters  as listing()
     * @param  list<int>  $attributeIds
     * @return array{values: array<int, int>, price: array{min: ?float, max: ?float}}
     */
    public function facets(array $filters, array $attributeIds): array
    {
        if (! self::unfiltered($filters)) {
            return $this->products->storefrontFacets($filters, $attributeIds);
        }

        $key = 'facets.'.($filters['category'] ?? 'all').'.'.implode('-', $attributeIds);

        return $this->cache->remember($key, fn () => $this->products->storefrontFacets($filters, $attributeIds));
    }

    /**
     * The attributes the shop filters by, with their active values in order: names in this locale,
     * swatches for colors.
     *
     * @return list<array{id: int, name: string, swatch: bool, values: list<array{id: int, name: string, color: ?string}>}>
     */
    public function filterableAttributes(): array
    {
        return $this->cache->remember('filter-attributes.'.app()->getLocale(), fn () => $this->attributes->filterable()
            ->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'name' => (string) ($attribute->name ?: $attribute->translations->first()?->name),
                'swatch' => (int) $attribute->type === AttributeType::COLOR->value,
                'values' => $attribute->attributeValues
                    ->map(fn (AttributeValue $value) => [
                        'id' => $value->id,
                        'name' => (string) ($value->name ?: $value->translations->first()?->name),
                        'color' => (int) $attribute->type === AttributeType::COLOR->value && filled($value->color_code) ? $value->color_code : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all());
    }

    /**
     * Active root categories in their sort order, as the home tiles, the shop chips and the
     * categories page show them.
     *
     * @return list<array{id: int, slug: string, name: string, description: ?string, image: ?string, url: string}>
     */
    public function categoryList(): array
    {
        // "category-list", not "categories": entries cached before the url and description were added never match.
        return $this->cache->remember('category-list.'.app()->getLocale(), fn () => $this->categories()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => (string) ($category->name ?: $category->translations->first()?->name),
                'description' => filled($category->description) ? (string) $category->description : null,
                'image' => $category->getFirstMediaUrl('category') ?: null,
                'url' => route('store.category', ['slug' => $category->slug]),
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

    /**
     * The category and its active descendants, as the shop filters by them.
     *
     * @return list<int>
     */
    public function categoryIds(Category $category): array
    {
        return $this->cache->remember('category-ids.'.$category->getKey(), fn () => array_map('intval', $this->categories->withDescendantIds($category)));
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
        $rows = $this->cache->remember('related.'.app()->getLocale().'.'.$product->getKey().'.'.$limit, fn () => $this->rows($this->products->relatedForStorefront($product, $limit)));

        return $this->cards($rows);
    }

    /**
     * Cards of these products, in this order, leaving out those the storefront does not show (a
     * wishlist). Not cached: the list is one buyer's.
     *
     * @param  list<int>  $ids
     * @return Collection<int, ProductCard>
     */
    public function byIds(array $ids): Collection
    {
        return $this->cards($this->rows($this->products->publishedByIds($ids)));
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
     * The attribute whose values a card lists as its sizes (null: none), as the cache holds it.
     */
    private function sizeAttributeId(): ?int
    {
        return $this->cache->remember('size-attribute', fn () => ['id' => $this->attributes->sizeAttributeId()])['id'];
    }

    /**
     * No search, attribute or price filter: what every visitor of a page reads the same way.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function unfiltered(array $filters): bool
    {
        return blank($filters['q'] ?? null) && empty($filters['attributes'])
            && ($filters['price_min'] ?? null) === null && ($filters['price_max'] ?? null) === null;
    }

    /**
     * What a card needs, in a form the cache can hold.
     *
     * @param  iterable<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $products): array
    {
        $products = collect($products);
        $sizeAttributeId = $products->isEmpty() ? null : $this->sizeAttributeId();

        return $products
            ->map(fn (Product $product) => [
                ...ProductCard::facts($product, $sizeAttributeId),
                'pricing' => array_intersect_key($product->getAttributes(), array_flip(PricingService::COLUMNS)),
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
        return collect($rows)->map(function (array $row): ProductCard {
            $price = $this->pricing->for((new Product)->setRawAttributes($row['pricing']));

            return ProductCard::fromFacts($row, $price, $this->badges->for($row['badges'] ?? [], $price));
        });
    }
}
