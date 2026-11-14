<?php

namespace Modules\Store\Services;

use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;

/**
 * The header's suggestions while a buyer types: a few products (as the shop's search finds them,
 * best matches first) and categories. Read fresh on every request: two small queries, and a cache
 * keyed by everything buyers type would only fill the cache table.
 */
class StorefrontSearch
{
    public const MIN_LENGTH = 2;

    public const PRODUCTS = 6;

    public const CATEGORIES = 4;

    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private PricingService $pricing,
    ) {}

    /**
     * @return array{products: list<array{name: string, image: ?string, price: string, was: ?string, url: string}>, categories: list<array{name: string, url: string}>}
     */
    public function suggest(mixed $q): array
    {
        $q = self::clean($q);

        if (mb_strlen($q) < self::MIN_LENGTH) {
            return ['products' => [], 'categories' => []];
        }

        return [
            'products' => $this->products->searchSuggestions($q, self::PRODUCTS)->map(function (Product $product): array {
                $price = $this->pricing->for($product);

                return [
                    'name' => (string) ($product->name ?: $product->translations->first()?->name),
                    'image' => $product->getFirstMediaUrl('product') ?: null,
                    'price' => $price->format(),
                    'was' => $price->formatWas(),
                    'url' => route('store.product', ['slug' => $product->slug]),
                ];
            })->values()->all(),
            'categories' => $this->categories->searchActive($q, self::CATEGORIES)->map(fn (Category $category) => [
                'name' => (string) ($category->name ?: $category->translations->first()?->name),
                'url' => route('store.category', ['slug' => $category->slug]),
            ])->values()->all(),
        ];
    }

    /**
     * Valid UTF-8 without null bytes (PostgreSQL refuses them), at most StorefrontShop::MAX_SEARCH characters.
     */
    public static function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim(str_replace("\0", '', mb_scrub($value, 'UTF-8'))), 0, StorefrontShop::MAX_SEARCH);
    }
}
