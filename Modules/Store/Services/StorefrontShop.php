<?php

namespace Modules\Store\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Items\Entities\Category;
use Modules\Store\Support\ProductCard;

/**
 * The shop grid's props, for every product or one category: search, sort and page come from the
 * URL. A URL can carry anything, so they are cleaned before they reach a query.
 */
class StorefrontShop
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'featured'];

    public const MAX_SEARCH = 100;

    public function __construct(private StorefrontCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function props(Request $request, ?Category $category = null): array
    {
        $q = $this->search($request->query('q'));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'newest';
        $products = $this->catalog->listing(['category' => $category?->slug, 'q' => $q, 'sort' => $sort]);

        return [
            'category' => $category ? ['id' => $category->id, 'slug' => $category->slug, 'name' => (string) $category->name] : null,
            'categories' => array_map(fn (array $root) => [
                'id' => $root['id'],
                'name' => $root['name'],
                'url' => route('store.category', ['slug' => $root['slug']]),
            ], $this->catalog->categoryList()),
            'filters' => ['q' => $q, 'sort' => $sort],
            'products' => $this->page($products),
        ];
    }

    /**
     * Text only (`?q[]=` is an array), valid UTF-8 without null bytes (PostgreSQL refuses them).
     */
    private function search(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim(str_replace("\0", '', mb_scrub($value, 'UTF-8'))), 0, self::MAX_SEARCH);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $products): array
    {
        $products->appends(array_filter(request()->query(), 'is_string'));

        return [
            'data' => collect($products->items())->map(fn (ProductCard $card) => $card->toArray())->values()->all(),
            'total' => $products->total(),
            'total_label' => trans_choice(':count product|:count products', $products->total(), ['count' => $products->total()]),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'prev_url' => $products->previousPageUrl(),
            'next_url' => $products->nextPageUrl(),
            // Numbers and gaps, without the previous and next ends.
            'pages' => $products->linkCollection()->slice(1, -1)
                ->map(fn (array $link) => ['label' => (string) $link['label'], 'url' => $link['url'], 'active' => (bool) $link['active']])
                ->values()
                ->all(),
        ];
    }
}
