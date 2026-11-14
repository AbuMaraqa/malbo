<?php

namespace Modules\Items\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\BranchPrice;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\BundleStock;

class ProductRepository extends BaseEloquentRepository implements ProductRepositoryInterface
{
    public const STOREFRONT_PER_PAGE = 24;

    public function __construct(Product $model, private CategoryRepositoryInterface $categories)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()
            ->with(['createdBy', 'media', 'translations', 'categories.translations'])
            ->whereNull('parent_id')
            // Sets and bundles have their own list (BundleRepository).
            ->whereNull('bundle_type');
    }

    public function publishedForStorefront(): Builder
    {
        return $this->query()
            ->whereNull('parent_id')
            ->where('status', ProductStatus::PUBLISHED)
            ->where('is_active', ProductActive::IS_ACTIVE->value);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->query()->where('slug', $slug)->first();
    }

    public function storefrontListing(array $filters): LengthAwarePaginator
    {
        $query = $this->storefrontMatches($filters);
        $search = self::searchTerm($filters);
        // A search is sorted by relevance unless another order is asked for; without one, relevance means nothing.
        $sort = $filters['sort'] ?? ($search !== '' ? 'relevance' : 'newest');

        // By the price the buyer sees (PricingService, dated discounts included).
        [$price, $bindings] = $this->displayedPriceSql();

        match ($sort) {
            'relevance' => $search !== '' ? $this->rankByName($query, $search)->latest() : $query->latest(),
            'price_asc' => $query->orderByRaw("{$price} asc", $bindings),
            'price_desc' => $query->orderByRaw("{$price} desc", $bindings),
            'featured' => $query->orderByDesc('is_featured')->latest(),
            'best_selling' => $query->orderByDesc('sales_count')->latest(),
            default => $query->latest(),
        };

        return $query->with(self::cardRelations())->orderByDesc('id')->paginate(self::STOREFRONT_PER_PAGE)->withQueryString();
    }

    public function storefrontFacets(array $filters, array $attributeIds): array
    {
        $attributes = self::selectedValues($filters);
        $table = $this->model->getTable();
        $prefix = config('items.table_prefix');

        // Products of the other filters (price included), each value counted on the variants that
        // also hold the values chosen for the other attributes: one grouped query for every value.
        $parents = $this->storefrontMatches([...$filters, 'attributes' => []])->select($table.'.id')->toBase();

        $counts = $attributeIds === [] ? [] : $this->orderable(DB::table($table.' as fv'), 'fv')
            ->join("{$prefix}product_attribute_value as fpav", 'fpav.product_id', '=', 'fv.id')
            ->join("{$prefix}attribute_values as fval", 'fval.id', '=', 'fpav.attribute_id')
            ->whereIn('fval.attribute_id', $attributeIds)
            ->whereIn('fv.parent_id', $parents)
            ->where(function (QueryBuilder $query) use ($attributes, $prefix): void {
                foreach ($attributes as $attributeId => $valueIds) {
                    $query->where(fn (QueryBuilder $other) => $other
                        ->where('fval.attribute_id', $attributeId)
                        ->orWhereExists(fn (QueryBuilder $held) => $held
                            ->from("{$prefix}product_attribute_value as held_{$attributeId}")
                            ->whereColumn("held_{$attributeId}.product_id", 'fv.id')
                            ->whereIn("held_{$attributeId}.attribute_id", $valueIds)));
                }
            })
            ->groupBy('fpav.attribute_id')
            ->selectRaw('fpav.attribute_id as value_id, count(distinct fv.parent_id) as products')
            ->pluck('products', 'value_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        // The price bounds of what the other filters leave, the price filter aside.
        [$price, $bindings] = $this->displayedPriceSql();
        $bounds = $this->storefrontMatches([...$filters, 'price_min' => null, 'price_max' => null])
            ->toBase()
            ->selectRaw("min({$price}) as low, max({$price}) as high", [...$bindings, ...$bindings])
            ->first();

        return [
            'values' => $counts,
            'price' => [
                'min' => $bounds?->low === null ? null : (float) $bounds->low,
                'max' => $bounds?->high === null ? null : (float) $bounds->high,
            ],
        ];
    }

    /**
     * The price a storefront card shows, in SQL on the product's own columns: exactly
     * PricingService::for(). The sale price when above 0 and below the base price; then a
     * percentage (at most 100) or fixed discount while its window is open (a midnight end date
     * means the whole day); never below 0; 2 decimals. "Now" is bound from PHP, in the app's timezone.
     *
     * @return array{0: string, 1: list<string>} the expression and its bindings
     */
    private function displayedPriceSql(): array
    {
        $t = $this->model->getTable();
        $listed = "(case when {$t}.sale_price > 0 and {$t}.sale_price < coalesce({$t}.base_price, 0) then {$t}.sale_price else coalesce({$t}.base_price, 0) end)";
        $end = "(case when {$t}.discount_end_date::time = '00:00:00' then {$t}.discount_end_date + interval '1 day' - interval '1 microsecond' else {$t}.discount_end_date end)";
        $open = "{$t}.discount_type in ('percentage', 'fixed') and {$t}.discount_value > 0"
            ." and ({$t}.discount_start_date is null or {$t}.discount_start_date <= ?::timestamp)"
            ." and ({$t}.discount_end_date is null or {$end} >= ?::timestamp)";
        $off = "(case when {$open} then (case when {$t}.discount_type = 'percentage' then {$listed} * least({$t}.discount_value, 100) / 100 else {$t}.discount_value end) else 0 end)";
        $now = Carbon::now()->format('Y-m-d H:i:s.u');

        return ["greatest(round(({$listed} - {$off})::numeric, 2), 0)", [$now, $now]];
    }

    /**
     * Variants a buyer can see and order, as ProductConfigurator decides it: active and not deleted;
     * no inactive value (visible()); not marked out of stock, and unmanaged, open to backorders or
     * above 0 (available()).
     */
    private function orderable(QueryBuilder $variants, string $alias): QueryBuilder
    {
        return $this->visible($variants, $alias)
            ->whereRaw("{$alias}.stock_status is distinct from 'out_of_stock'")
            ->whereRaw("(coalesce({$alias}.manage_stock, false) = false or coalesce({$alias}.allow_backorder, false) = true or {$alias}.stock_quantity > 0)");
    }

    /**
     * Variants a buyer can see (ProductConfigurator::visible): active, not deleted, no inactive value.
     */
    private function visible(QueryBuilder $variants, string $alias): QueryBuilder
    {
        $prefix = config('items.table_prefix');

        return $variants
            ->whereNotNull($alias.'.parent_id')
            ->whereNull($alias.'.deleted_at')
            ->where($alias.'.is_active', ProductActive::IS_ACTIVE->value)
            ->whereNotExists(fn (QueryBuilder $hidden) => $hidden
                ->from("{$prefix}product_attribute_value as hidden_pav")
                ->join("{$prefix}attribute_values as hidden_val", 'hidden_val.id', '=', 'hidden_pav.attribute_id')
                ->whereColumn('hidden_pav.product_id', $alias.'.id')
                ->whereRaw('hidden_val.is_active is not true'));
    }

    /**
     * attribute id => value ids, as StorefrontShop cleaned them.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, list<int>>
     */
    private static function selectedValues(array $filters): array
    {
        return array_filter((array) ($filters['attributes'] ?? []), fn ($values) => is_array($values) && $values !== []);
    }

    public function searchSuggestions(string $term, int $limit = 6): EloquentCollection
    {
        $term = trim($term);
        $query = $this->storefrontMatches(['q' => $term])->with(['translations', 'media']);
        $this->rankByName($query, $term);

        return $query->latest()->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * The best name matches first: one equal to the search (any letter case), then one that starts with it.
     */
    private function rankByName(Builder $query, string $search): Builder
    {
        $translations = $this->model->translations()->getRelated()->getTable();
        $table = $this->model->getTable();
        $active = ProductActive::IS_ACTIVE->value;

        // 0: the product's or an active variant's SKU is the search; 1: a name is; 2: a name starts with it.
        return $query->orderByRaw(
            "case when exists (select 1 from {$table} as rank_sku"
            ." where (rank_sku.id = {$table}.id or (rank_sku.parent_id = {$table}.id and rank_sku.is_active = {$active} and rank_sku.deleted_at is null))"
            .' and lower(rank_sku.sku) = lower(?))'
            .' then 0 else coalesce((select min(case when items_search_norm(rank_t.name) = items_search_norm(?) then 1'
            .' when items_search_norm(rank_t.name) like items_search_norm(?) then 2 else 3 end)'
            ." from {$translations} rank_t where rank_t.product_id = {$table}.id), 3) end asc",
            [$search, $search, addcslashes($search, '%_\\').'%'],
        );
    }

    /**
     * The published parents the shop's filters leave: category (with its subcategories), search,
     * attribute values (one variant a buyer can see and order holds a chosen value of every filtered
     * attribute) and the displayed price.
     *
     * @param  array<string, mixed>  $filters
     */
    private function storefrontMatches(array $filters): Builder
    {
        $query = $this->publishedForStorefront();
        $table = $this->model->getTable();
        $prefix = config('items.table_prefix');

        // The caller may pass the ids it already has (the shop reads them once for the listing and its facets).
        $categoryIds = array_key_exists('category_ids', $filters) ? $filters['category_ids'] : null;

        if ($categoryIds === null && filled($slug = $filters['category'] ?? null)) {
            $categoryIds = ($category = $this->categories->findActiveBySlug($slug)) ? $this->categories->withDescendantIds($category) : [];
        }

        if ($categoryIds !== null) {
            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryIds));
        }

        if (filled($search = self::searchTerm($filters))) {
            $this->search($query, $search);
        }

        if (($attributes = self::selectedValues($filters)) !== []) {
            $query->whereExists(function (QueryBuilder $variants) use ($attributes, $table, $prefix): void {
                $this->orderable($variants->from($table.' as mv'), 'mv')->whereColumn('mv.parent_id', $table.'.id');

                foreach ($attributes as $attributeId => $valueIds) {
                    $variants->whereExists(fn (QueryBuilder $held) => $held
                        ->from("{$prefix}product_attribute_value as mv_{$attributeId}")
                        ->whereColumn("mv_{$attributeId}.product_id", 'mv.id')
                        ->whereIn("mv_{$attributeId}.attribute_id", $valueIds));
                }
            });
        }

        $min = $filters['price_min'] ?? null;
        $max = $filters['price_max'] ?? null;

        if ($min !== null || $max !== null) {
            [$price, $bindings] = $this->displayedPriceSql();

            if ($min !== null) {
                $query->whereRaw("{$price} >= ?", [...$bindings, (float) $min]);
            }

            if ($max !== null) {
                $query->whereRaw("{$price} <= ?", [...$bindings, (float) $max]);
            }
        }

        return $query;
    }

    /**
     * A name in any language, the product's or a variant's SKU, a tag's name or an active category's
     * name contains the term, whatever the letter case (ILIKE, read through the trigram indexes).
     */
    private function search(Builder $query, string $search): void
    {
        $prefix = config('items.table_prefix');
        $table = $this->model->getTable();

        // Every word must be found somewhere: "قميص اسود" is a shirt that comes in black.
        foreach (self::words($search) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            // Both sides in one spelling (items_search_norm: letter forms, marks, case).
            $named = fn ($translations) => $translations->whereRaw('items_search_norm(name) like items_search_norm(?)', [$like]);

            $query->where(fn (Builder $query) => $query
                ->whereHas('translations', $named)
                ->orWhereLike($query->qualifyColumn('sku'), $like, caseSensitive: false)
                ->orWhereHas('variations', fn (Builder $variations) => $variations
                    ->where('is_active', ProductActive::IS_ACTIVE->value)
                    ->whereLike('sku', $like, caseSensitive: false))
                ->orWhereHas('tags.translations', $named)
                ->orWhereHas('categories', fn (Builder $categories) => $categories
                    ->where('status', CategoryStatus::ACTIVE)
                    ->whereHas('translations', $named))
                // A value of a variant the buyer can see: its color, its size...
                ->orWhereExists(fn (QueryBuilder $variants) => $this->visible($variants->from($table.' as sv'), 'sv')
                    ->whereColumn('sv.parent_id', $table.'.id')
                    ->whereExists(fn (QueryBuilder $values) => $values
                        ->from("{$prefix}product_attribute_value as spav")
                        ->join("{$prefix}attribute_values as sval", 'sval.id', '=', 'spav.attribute_id')
                        ->join("{$prefix}attribute_values_translations as svt", 'svt.attribute_value_id', '=', 'sval.id')
                        ->whereColumn('spav.product_id', 'sv.id')
                        ->where('sval.is_active', true)
                        ->whereRaw('items_search_norm(svt.name) like items_search_norm(?)', [$like]))));
        }
    }

    /**
     * The search's words, at most 6 (the rest is ignored).
     *
     * @return list<string>
     */
    private static function words(string $search): array
    {
        return array_slice(array_values(array_unique(preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [])), 0, 6);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function searchTerm(array $filters): string
    {
        return trim((string) ($filters['q'] ?? ''));
    }

    public function featuredForStorefront(int $limit = 8): EloquentCollection
    {
        $featured = $this->publishedForStorefront()->with(self::cardRelations())
            ->where('is_featured', true)->latest()->orderByDesc('id')->limit($limit)->get();

        return $featured->concat($this->newestExcept($featured->modelKeys(), $limit - $featured->count()));
    }

    public function findPublishedBySlug(string $slug): ?Product
    {
        return $this->publishedForStorefront()
            ->with([
                ...self::cardRelations(),
                // The whole rows: the page shows them and their values.
                'variations' => fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value),
                'variations.attributeValues' => fn ($values) => $values,
                'variations.translations', 'variations.media',
                'variations.attributeValues.translations', 'variations.attributeValues.attribute.translations',
                'addons.translations', 'addons.addonOptions.translations',
                // A set or bundle: its components, their choices and names (Modules\Items\Support\BundleStock).
                ...BundleStock::relations(full: true),
            ])
            ->where('slug', $slug)
            ->first();
    }

    public function storefrontSlugs(): Collection
    {
        return $this->publishedForStorefront()->toBase()->orderBy('id')->get(['slug', 'updated_at']);
    }

    public function relatedForStorefront(Product $product, int $limit = 4): EloquentCollection
    {
        $categories = $product->relationLoaded('categories') ? $product->categories : $product->categories()->get();
        $categoryIds = collect($categories->modelKeys());
        // The most specific one: none of the product's other categories sits under it.
        $parents = $categories->pluck('parent_id')->filter()->map(fn ($id) => (int) $id);
        $deepest = $categories->first(fn ($category) => ! $parents->contains((int) $category->getKey()))?->getKey();

        $related = $categoryIds->isEmpty() ? new EloquentCollection : $this->publishedForStorefront()
            ->with(self::cardRelations())
            ->whereKeyNot($product->getKey())
            ->whereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryIds))
            ->tap(fn (Builder $query) => $this->sharedFilterableValuesFirst($query, $product))
            ->when($deepest, fn (Builder $query) => $query->orderByRaw(
                'exists (select 1 from '.config('items.table_prefix').'products_categories as deep_pc where deep_pc.product_id = '.$this->model->getTable().'.id and deep_pc.category_id = ?) desc',
                [$deepest],
            ))
            ->latest()->orderByDesc('id')->limit($limit)->get();

        return $related->concat($this->newestExcept([...$related->modelKeys(), $product->getKey()], $limit - $related->count()));
    }

    /**
     * Products whose active variants hold more of the filterable values (a color, a size...) this
     * product's active variants hold come first.
     */
    private function sharedFilterableValuesFirst(Builder $query, Product $product): void
    {
        $prefix = config('items.table_prefix');
        $table = $this->model->getTable();
        $active = ProductActive::IS_ACTIVE->value;

        $query->orderByRaw(<<<SQL
            (
                select count(distinct rel_pav.attribute_id)
                from {$table} as rel_variant
                join {$prefix}product_attribute_value as rel_pav on rel_pav.product_id = rel_variant.id
                join {$prefix}product_attribute_value as own_pav on own_pav.attribute_id = rel_pav.attribute_id
                join {$table} as own_variant on own_variant.id = own_pav.product_id
                join {$prefix}attribute_values as rel_value on rel_value.id = rel_pav.attribute_id
                join {$prefix}attributes as rel_attribute on rel_attribute.id = rel_value.attribute_id
                where rel_variant.parent_id = {$table}.id and rel_variant.deleted_at is null and rel_variant.is_active = {$active}
                    and own_variant.parent_id = ? and own_variant.deleted_at is null and own_variant.is_active = {$active}
                    and rel_attribute.is_filterable = true
            ) desc
            SQL, [$product->getKey()]);
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'product'; // a name of symbols only has no slug
        $candidate = $base;

        for ($counter = 1; $this->slugTaken($candidate, $ignoreId); $counter++) {
            $candidate = $base.'-'.$counter;
        }

        return $candidate;
    }

    public function forEdit(int|string $id): Product
    {
        return $this->query()->with([
            'translations', 'media', 'variations' => fn ($variations) => $variations->orderBy('id'),
            'variations.translations', 'variations.categories', 'variations.attributeValues',
            'variations.media', 'tags.translations', 'labels.translations', 'categories', 'branches',
            'addons.translations', 'addons.addonOptions.translations',
        ])->findOrFail($id);
    }

    public function withVariations(int|string $id): Product
    {
        // A set or bundle has neither barcodes of its own nor branch prices: its own form only.
        return $this->query()
            ->whereNull('bundle_type')
            ->with(['translations', 'variations' => fn ($variations) => $variations->orderBy('id'), 'variations.translations'])
            ->findOrFail($id);
    }

    public function findProductOrFail(int $id): Product
    {
        /** @var Product */
        return $this->query()->whereNull('bundle_type')->findOrFail($id);
    }

    public function isBundle(int $id): bool
    {
        return $this->query()->whereKey($id)->whereNotNull('bundle_type')->exists();
    }

    public function branchPrices(iterable $productIds): Collection
    {
        return BranchPrice::query()->whereIn('product_id', collect($productIds))->get()
            ->keyBy(fn (BranchPrice $price) => "{$price->product_id}:{$price->branch_id}");
    }

    public function parentOptionsForOffers(): array
    {
        return $this->query()->with(['translations', 'branches'])->whereNull('parent_id')->orderBy('id')->get()
            ->map(fn (Product $product) => [
                'value' => $product->id,
                'label' => (string) $product->name,
                'branches' => $product->branches->modelKeys(),
            ])
            ->all();
    }

    public function isParentInBranches(int $productId, array $branchIds): bool
    {
        return $this->query()
            ->whereKey($productId)
            ->whereNull('parent_id')
            ->whereHas('branches', fn (Builder $branches) => $branches->whereKey($branchIds))
            ->exists();
    }

    public function searchSellable(string $term, array $include = [], int $limit = 50): EloquentCollection
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';
        $named = fn (Builder $translations) => $translations->whereLike('name', $like, caseSensitive: false);

        return $this->sellable($this->query()->with('translations'))
            ->where(fn (Builder $query) => $query
                ->whereLike('sku', $like, caseSensitive: false)
                ->orWhereHas('translations', $named)
                ->orWhereHas('parent.translations', $named)
                ->when($include !== [], fn (Builder $query) => $query->orWhereKey($include)))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function isSellable(int $id): bool
    {
        return $this->sellable($this->query()->whereKey($id))->exists();
    }

    public function lowStockQuery(): Builder
    {
        $table = $this->model->getTable();

        $forSale = fn (Builder $query) => $query
            ->where($query->qualifyColumn('status'), ProductStatus::PUBLISHED)
            ->where($query->qualifyColumn('is_active'), ProductActive::IS_ACTIVE->value);

        return $this->sellable($this->query())
            ->where($table.'.manage_stock', true)
            ->whereRaw("{$table}.stock_quantity <= coalesce({$table}.low_stock_threshold, 0)")
            // Only what the store sells: a product published and active like the storefront's, and a
            // variant that is active itself with a parent published and active.
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $single) => $forSale($single->whereNull($table.'.parent_id')))
                ->orWhere(fn (Builder $variant) => $variant
                    ->whereNotNull($table.'.parent_id')
                    ->where($table.'.is_active', ProductActive::IS_ACTIVE->value)
                    ->whereHas('parent', $forSale)));
    }

    /**
     * What can go on an order line and holds stock: variants (their parent not deleted), and
     * products that have none. Stock is kept per variant: a product with variants is sold through them.
     * A set or bundle holds no stock and is sold on the storefront only: never.
     */
    private function sellable(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('bundle_type'))->where(fn (Builder $query) => $query
            ->whereHas('parent')
            ->orWhere(fn (Builder $single) => $single->whereNull('parent_id')->whereDoesntHave('variations')));
    }

    public function lookOptions(Product $product): EloquentCollection
    {
        return $product->looks()->with('translations')->get();
    }

    public function completeTheLook(Product $product): EloquentCollection
    {
        $table = $this->model->getTable();

        return $product->looks()
            ->where($table.'.status', ProductStatus::PUBLISHED)
            ->where($table.'.is_active', ProductActive::IS_ACTIVE->value)
            ->whereNull($table.'.bundle_type')
            ->with(self::cardRelations())
            ->get();
    }

    public function searchParents(string $term, int $limit = 50, bool $withBundles = false): EloquentCollection
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $this->query()->with('translations')
            ->whereNull('parent_id')
            ->when(! $withBundles, fn (Builder $query) => $query->whereNull('bundle_type'))
            ->when(trim($term) !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('sku', $like, caseSensitive: false)
                ->orWhereHas('translations', fn (Builder $translations) => $translations->whereLike('name', $like, caseSensitive: false))))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function trashedVariation(Product $parent, string $slug): ?Product
    {
        return $this->query()->onlyTrashed()->where('parent_id', $parent->id)->where('slug', $slug)->first();
    }

    public function addonOptionOverrides(iterable $addonProductIds): Collection
    {
        $ids = collect($addonProductIds)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table(config('items.table_prefix').'addon_product_option')
            ->whereIn('addon_product_id', $ids)
            ->get()
            ->groupBy('addon_product_id');
    }

    public function variationAttributeValueIds(Product $product): Collection
    {
        $prefix = config('items.table_prefix');

        return DB::table("{$prefix}product_attribute_value AS pav")
            ->join("{$prefix}products AS variations", 'variations.id', '=', 'pav.product_id')
            ->where('variations.parent_id', $product->id)
            ->whereNull('variations.deleted_at')
            ->pluck('pav.attribute_id')
            ->unique()
            ->values();
    }

    public function changedSince(string $since): EloquentCollection
    {
        return $this->query()
            ->with([
                'media',
                'translations',
                'categories.media',
                'categories.translations',
                'addons.media',
                'addons.translations',
                'addons.addonOptions.media',
                'addons.addonOptions.translations',
            ])
            ->withExists('variations')
            // The app cannot sell a set or bundle (OrderService::place refuses it): not synced.
            ->whereNull('bundle_type')
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * What a storefront product card needs; only active categories (an inactive one would label
     * the card and link to a missing page).
     */
    public function forCart(array $ids): EloquentCollection
    {
        if ($ids === []) {
            return new EloquentCollection;
        }

        return $this->query()->withTrashed()
            ->with([
                'translations', 'media', 'addons',
                'categories' => fn ($categories) => $categories->select($categories->qualifyColumn('id')),
                'attributeValues.translations', 'attributeValues.attribute.translations',
                'parent' => fn ($parent) => $parent->withTrashed(),
                'parent.translations', 'parent.media', 'parent.addons',
                'parent.categories' => fn ($categories) => $categories->select($categories->qualifyColumn('id')),
                'variations' => fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value)->select(['id', 'parent_id']),
                // A set or bundle in the cart: its components, the variants chosen and their names.
                ...BundleStock::relations(full: true),
            ])
            ->whereKey($ids)
            ->get()
            ->keyBy('id');
    }

    public function publishedByIds(array $ids): EloquentCollection
    {
        if ($ids === []) {
            return new EloquentCollection;
        }

        $found = $this->publishedForStorefront()->with(self::cardRelations())->whereKey($ids)->get()->keyBy('id');

        return new EloquentCollection(array_values(array_filter(array_map(fn (int $id) => $found->get($id), $ids))));
    }

    public function siblingVariants(int $parentId): EloquentCollection
    {
        return $this->query()
            ->with(['translations', 'attributeValues.translations', 'attributeValues.attribute.translations'])
            ->where('parent_id', $parentId)
            ->where('is_active', ProductActive::IS_ACTIVE->value)
            ->orderBy('id')
            ->get();
    }

    private static function cardRelations(): array
    {
        return [
            'translations',
            'media',
            // In the order they were given: the first is the breadcrumb, and the size guide is the first one's that has it.
            'categories' => fn ($categories) => $categories->where('status', CategoryStatus::ACTIVE)->orderBy(config('items.table_prefix').'products_categories.id'),
            'categories.translations',
            // The badges (Modules\Store\Services\ProductBadges): labels, and the stock and price columns
            // of the active variations, with whether each value is shown (a hidden one hides its variation).
            'labels.translations',
            'variations' => fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value)
                ->select(['id', 'parent_id', 'manage_stock', 'allow_backorder', 'stock_quantity', 'stock_status',
                    'base_price', 'sale_price', 'discount_type', 'discount_value', 'discount_start_date', 'discount_end_date']),
            // Their attribute, order and names: the card's sizes (Modules\Store\Support\CardSizes).
            'variations.attributeValues' => fn ($values) => $values->select([
                $values->qualifyColumn('id'), $values->qualifyColumn('is_active'), $values->qualifyColumn('attribute_id'), $values->qualifyColumn('sort_order'),
            ]),
            'variations.attributeValues.translations',
            // A set or bundle: what its stock badges read (BundleStock). Empty for other products.
            ...BundleStock::relations(),
        ];
    }

    private function newestExcept(array $ids, int $limit): EloquentCollection
    {
        if ($limit <= 0) {
            return new EloquentCollection;
        }

        return $this->publishedForStorefront()->with(self::cardRelations())
            ->whereKeyNot($ids)->latest()->orderByDesc('id')->limit($limit)->get();
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        return $this->query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function countedProductIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->query()->withTrashed()->toBase()
            ->whereIn('id', $ids)
            ->selectRaw('id, coalesce(parent_id, id) as counted_id')
            ->pluck('counted_id', 'id')
            ->mapWithKeys(fn ($countedId, $id) => [(int) $id => (int) $countedId])
            ->all();
    }

    public function lockForStock(array $ids): EloquentCollection
    {
        return $this->query()
            ->withTrashed()
            ->with('translations')
            ->whereKey($ids)
            ->orderBy('id')
            // Not FOR UPDATE: it conflicts with the FOR KEY SHARE an order line's foreign key check
            // takes, which deadlocks two orders for the same product. Stock writes still exclude each other.
            ->lock('for no key update')
            ->get();
    }
}
