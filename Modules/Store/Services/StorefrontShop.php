<?php

namespace Modules\Store\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Items\Entities\Category;
use Modules\Store\Support\ProductCard;

/**
 * The shop grid's props, for every product or one category: search, filters, sort and page come
 * from the URL. A URL can carry anything, so they are cleaned before they reach a query:
 * - `attr[<attribute id>]=<value id>,<value id>`: only filterable attributes and their active values;
 * - `price_min`, `price_max`: numbers of 0 or more, put in order;
 * - `q`: text, `sort`: one of SORTS.
 * The filters stay in the pagination links, so a shared or paged URL shows the same products.
 */
class StorefrontShop
{
    /** `relevance` only with a search: it is then the default. */
    public const SORTS = ['newest', 'relevance', 'best_selling', 'price_asc', 'price_desc', 'featured'];

    public const MAX_SEARCH = 100;

    /** Values a single attribute may filter by at once. */
    private const MAX_VALUES = 50;

    /** decimal(10, 2) */
    private const MAX_PRICE = 99999999.99;

    public function __construct(private StorefrontCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function props(Request $request, ?Category $category = null): array
    {
        $attributes = $this->catalog->filterableAttributes();
        $q = StorefrontSearch::clean($request->query('q'));
        $default = $q !== '' ? 'relevance' : 'newest';
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : $default;
        $sort = $sort === 'relevance' && $q === '' ? 'newest' : $sort;
        $selected = $this->selected($request->query('attr'), $attributes);
        [$min, $max] = $this->priceRange($request->query('price_min'), $request->query('price_max'));

        $filters = [
            'category' => $category?->slug,
            ...($category ? ['category_ids' => $this->catalog->categoryIds($category)] : []),
            'q' => $q, 'sort' => $sort, 'attributes' => $selected, 'price_min' => $min, 'price_max' => $max,
        ];
        $products = $this->catalog->listing($filters);
        $facets = $this->catalog->facets($filters, array_column($attributes, 'id'));

        return [
            'category' => $category ? [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => (string) ($category->name ?: $category->translations->first()?->name),
                'description' => filled($category->description) ? (string) $category->description : null,
                'image' => $category->getFirstMediaUrl('category') ?: null,
            ] : null,
            'categories' => array_map(fn (array $root) => [
                'id' => $root['id'],
                'name' => $root['name'],
                'url' => route('store.category', ['slug' => $root['slug']]),
            ], $this->catalog->categoryList()),
            // An object even when empty, so the browser reads it by attribute id.
            'filters' => ['q' => $q, 'sort' => $sort, 'attr' => (object) $selected, 'price_min' => $min, 'price_max' => $max],
            'facets' => [
                'attributes' => $this->facetAttributes($attributes, $facets['values'], $selected),
                'price' => $facets['price'],
            ],
            'products' => $this->page($products, array_filter([
                'q' => $q,
                'sort' => $sort === $default ? null : $sort,
                'attr' => array_map(fn (array $ids) => implode(',', $ids), $selected),
                'price_min' => $min,
                'price_max' => $max,
            ], fn ($value) => $value !== null && $value !== '' && $value !== [])),
        ];
    }

    /**
     * attribute id => value ids, keeping only filterable attributes and their active values.
     *
     * @param  list<array{id: int, values: list<array{id: int}>}>  $attributes
     * @return array<int, list<int>>
     */
    private function selected(mixed $query, array $attributes): array
    {
        if (! is_array($query)) {
            return [];
        }

        $selected = [];

        foreach ($attributes as $attribute) {
            $given = $query[$attribute['id']] ?? $query[(string) $attribute['id']] ?? null;
            $given = is_string($given) ? explode(',', $given) : (is_array($given) ? $given : []);
            $allowed = array_column($attribute['values'], 'id');

            $ids = array_values(array_unique(array_map('intval', array_filter(
                array_slice($given, 0, self::MAX_VALUES),
                fn ($id) => is_string($id) && ctype_digit(trim($id)) && in_array((int) $id, $allowed, true),
            ))));

            if ($ids !== []) {
                $selected[$attribute['id']] = $ids;
            }
        }

        return $selected;
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function priceRange(mixed $min, mixed $max): array
    {
        $clean = fn (mixed $value) => is_string($value) && is_numeric(trim($value)) && (float) $value >= 0
            ? round(min((float) $value, self::MAX_PRICE), 2)
            : null;

        $min = $clean($min);
        $max = $clean($max);

        return $min !== null && $max !== null && $min > $max ? [$max, $min] : [$min, $max];
    }

    /**
     * Each filterable attribute with the values that would show products, and those chosen.
     *
     * @param  list<array{id: int, name: string, swatch: bool, values: list<array{id: int, name: string, color: ?string}>}>  $attributes
     * @param  array<int, int>  $counts  value id => products
     * @param  array<int, list<int>>  $selected
     * @return list<array<string, mixed>>
     */
    private function facetAttributes(array $attributes, array $counts, array $selected): array
    {
        $facets = [];

        foreach ($attributes as $attribute) {
            $values = [];

            foreach ($attribute['values'] as $value) {
                $chosen = in_array($value['id'], $selected[$attribute['id']] ?? [], true);
                $count = $counts[$value['id']] ?? 0;

                if ($count > 0 || $chosen) {
                    $values[] = [...$value, 'count' => $count, 'selected' => $chosen];
                }
            }

            if ($values !== []) {
                $facets[] = ['id' => $attribute['id'], 'name' => $attribute['name'], 'swatch' => $attribute['swatch'], 'values' => $values];
            }
        }

        return $facets;
    }

    /**
     * @param  array<string, mixed>  $query  the cleaned filters, for the page links
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $products, array $query): array
    {
        $products->appends($query);

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
