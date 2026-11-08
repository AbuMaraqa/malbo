<?php

namespace Modules\Items\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
            ->whereNull('parent_id');
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
        $query = $this->publishedForStorefront()->with(self::cardRelations());

        if (filled($filters['category'] ?? null)) {
            $category = $this->categories->findActiveBySlug($filters['category']);
            $ids = $category ? $this->categories->withDescendantIds($category) : [];
            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($ids));
        }

        if (filled($search = trim((string) ($filters['q'] ?? '')))) {
            // whereLike is case-insensitive on PostgreSQL (ILIKE); whereTranslationLike is not.
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('translations', fn (Builder $translations) => $translations->whereLike('name', $term));
        }

        // By list price: the sale price when above 0 and lower than the base price. Dated discounts
        // (PricingService) are not part of the sort.
        $price = 'CASE WHEN sale_price > 0 AND sale_price < base_price THEN sale_price ELSE base_price END';

        match ($filters['sort'] ?? null) {
            'price_asc' => $query->orderByRaw("{$price} asc"),
            'price_desc' => $query->orderByRaw("{$price} desc"),
            'featured' => $query->orderByDesc('is_featured')->latest(),
            default => $query->latest(),
        };

        return $query->orderByDesc('id')->paginate(self::STOREFRONT_PER_PAGE)->withQueryString();
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
                'variations' => fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value),
                'variations.translations', 'variations.media',
                'variations.attributeValues.translations', 'variations.attributeValues.attribute.translations',
                'addons.translations', 'addons.addonOptions.translations',
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
        $categoryIds = $product->relationLoaded('categories')
            ? collect($product->categories->modelKeys())
            : $product->categories()->pluck($product->categories()->getRelated()->getQualifiedKeyName());

        $related = $categoryIds->isEmpty() ? new EloquentCollection : $this->publishedForStorefront()
            ->with(self::cardRelations())
            ->whereKeyNot($product->getKey())
            ->whereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryIds))
            ->latest()->orderByDesc('id')->limit($limit)->get();

        return $related->concat($this->newestExcept([...$related->modelKeys(), $product->getKey()], $limit - $related->count()));
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
        return $this->query()
            ->with(['translations', 'variations' => fn ($variations) => $variations->orderBy('id'), 'variations.translations'])
            ->findOrFail($id);
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

    public function searchParents(string $term, array $include = [], int $limit = 50): EloquentCollection
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $this->query()->with('translations')
            ->whereNull('parent_id')
            ->where(fn (Builder $query) => $query
                ->whereLike('sku', $like)
                ->orWhereHas('translations', fn (Builder $translations) => $translations->whereLike('name', $like))
                ->when($include !== [], fn (Builder $query) => $query->orWhereKey($include)))
            ->orderBy('id')
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
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * What a storefront product card needs; only active categories (an inactive one would label
     * the card and link to a missing page).
     */
    private static function cardRelations(): array
    {
        return [
            'translations',
            'media',
            'categories' => fn ($categories) => $categories->where('status', CategoryStatus::ACTIVE),
            'categories.translations',
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
}
