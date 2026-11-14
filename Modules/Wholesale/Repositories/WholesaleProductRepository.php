<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;

class WholesaleProductRepository implements WholesaleProductRepositoryInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function catalog(?int $tierId, array $filters, int $perPage = 24): LengthAwarePaginator
    {
        return $this->catalogQuery($tierId, $filters)->paginate($perPage)->withQueryString();
    }

    public function forCatalogPdf(?int $tierId, ?string $category, int $limit): Collection
    {
        return $this->catalogQuery($tierId, ['category' => $category])->limit($limit)->get();
    }

    /**
     * @param  array{q?: ?string, category?: ?string}  $filters
     */
    private function catalogQuery(?int $tierId, array $filters): Builder
    {
        $query = $this->trees()->where(fn (Builder $query) => $this->soldWholesale($query, $tierId));

        if (filled($filters['category'] ?? null)) {
            $category = $this->categories->findActiveBySlug((string) $filters['category']);
            $ids = $category ? $this->categories->withDescendantIds($category) : [];
            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($ids));
        }

        if (filled($search = trim((string) ($filters['q'] ?? '')))) {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $query) => $query
                ->whereHas('translations', fn (Builder $translations) => $translations->whereLike('name', $term))
                ->orWhereLike('sku', $term));
        }

        return $query->latest()->orderByDesc('id');
    }

    public function findPublishedBySlug(string $slug): ?Product
    {
        /** @var Product|null */
        return $this->trees()->where('slug', $slug)->first();
    }

    public function findPublished(int $id): ?Product
    {
        return $this->publishedTrees([$id])->get($id);
    }

    public function publishedTrees(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return $this->trees()->whereKey($ids)->get()->keyBy('id');
    }

    public function familyIds(int $productId): array
    {
        return $this->products->query()->withTrashed()
            ->where(fn (Builder $query) => $query->whereKey($productId)->orWhere('parent_id', $productId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function adminTableQuery(): Builder
    {
        return $this->products->query()
            ->whereNull('parent_id')
            // Sets and bundles are sold on the storefront only.
            ->whereNull('bundle_type')
            ->with(['translations', 'categories.translations'])
            ->withCount('variations');
    }

    public function forPriceEdit(int $id): Product
    {
        /** @var Product */
        return $this->products->query()
            ->whereNull('parent_id')
            // Sets and bundles are sold on the storefront only: no wholesale prices.
            ->whereNull('bundle_type')
            ->with(['translations', 'variations.translations', 'variations.attributeValues.translations', 'variations.attributeValues.attribute.translations'])
            ->findOrFail($id);
    }

    public function setByPiece(int $productId, bool $byPiece): void
    {
        $this->products->query()->whereKey($productId)->update(['wholesale_by_piece' => $byPiece]);
    }

    /**
     * What WholesaleItems::of() and PriceBook would sell, as a query, so a page is cut after it is
     * filtered:
     * - a product without variations (none at all, deleted ones aside) with a wholesale price above
     *   0 or a price of its own in this tier;
     * - or one with a sellable variation (active, none of its values hidden) that has a wholesale
     *   price, a price of its own in this tier, or falls back to the product's wholesale price.
     * A product whose variations are all inactive is not sold: never as a product of its own.
     */
    private function soldWholesale(Builder $query, ?int $tierId): void
    {
        $priced = fn () => TierPrice::query()->select('product_id')->where('tier_id', $tierId);

        $query->where(fn (Builder $single) => $single
            ->whereDoesntHave('variations')
            ->where(fn (Builder $price) => $price
                ->where('wholesale_price', '>', 0)
                ->when($tierId !== null, fn (Builder $price) => $price->orWhereIn($price->qualifyColumn('id'), $priced()))))
            ->orWhere(fn (Builder $parent) => $parent
                ->where('wholesale_price', '>', 0)
                ->whereHas('variations', fn (Builder $variations) => self::sellableVariation($variations)))
            ->orWhereHas('variations', fn (Builder $variations) => self::sellableVariation($variations)
                ->where(fn (Builder $price) => $price
                    ->where('wholesale_price', '>', 0)
                    ->when($tierId !== null, fn (Builder $price) => $price->orWhereIn($price->qualifyColumn('id'), $priced()))));
    }

    /**
     * Active, and none of its option values hidden (ProductConfigurator::visible).
     */
    private static function sellableVariation(Builder $variations): Builder
    {
        return $variations->where('is_active', ProductActive::IS_ACTIVE->value)
            ->whereDoesntHave('attributeValues', fn (Builder $values) => $values->where($values->qualifyColumn('is_active'), false));
    }

    /**
     * Published, active products as the portal reads them; `variations_count` counts every
     * variation that is not deleted, active or not.
     */
    private function trees(): Builder
    {
        // Sets and bundles are sold on the storefront only (B2C): never in the portal or its catalog.
        return $this->products->publishedForStorefront()->whereNull('bundle_type')->with(self::treeRelations())->withCount('variations');
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function treeRelations(): array
    {
        return [
            'translations',
            'media',
            'categories' => fn ($categories) => $categories->where('status', CategoryStatus::ACTIVE),
            'categories.translations',
            'variations' => fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value)->orderBy('id'),
            'variations.translations',
            'variations.media',
            'variations.attributeValues.translations',
            'variations.attributeValues.attribute.translations',
        ];
    }
}
