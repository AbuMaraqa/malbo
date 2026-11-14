<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\BundleRepositoryInterface;
use Modules\Items\Support\BundleStock;

class BundleRepository extends BaseEloquentRepository implements BundleRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()
            ->whereNotNull('bundle_type')
            ->with(['translations', 'media', 'categories.translations'])
            ->withCount('bundleComponents');
    }

    public function forEdit(int $id): Product
    {
        /** @var Product */
        return $this->query()
            ->whereNotNull('bundle_type')
            ->with([
                'translations', 'media', 'categories',
                'bundleComponents.product' => fn ($product) => $product->withTrashed(),
                'bundleComponents.product.translations',
                'bundleComponents.product.parent' => fn ($parent) => $parent->withTrashed(),
                'bundleComponents.product.parent.translations',
                'bundleComponents.product.attributeValues.translations',
                'bundleComponents.product.variations' => fn ($variations) => $variations->select(['id', 'parent_id']),
            ])
            ->findOrFail($id);
    }

    public function withComponents(int $id): ?Product
    {
        /** @var Product|null */
        return $this->query()->whereNotNull('bundle_type')->with(BundleStock::relations())->find($id);
    }

    public function componentOptions(string $term, array $include = [], int $limit = 50): Collection
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';
        $named = fn (Builder $translations) => $translations->whereLike('name', $like, caseSensitive: false);

        return $this->query()
            ->with([
                'translations', 'parent.translations', 'attributeValues.translations',
                'variations' => fn ($variations) => $variations->select(['id', 'parent_id']),
            ])
            ->whereNull('bundle_type')
            // A variant whose parent is gone is not offered.
            ->where(fn (Builder $query) => $query->whereNull('parent_id')->orWhereHas('parent'))
            ->where(fn (Builder $query) => $query
                ->whereLike('sku', $like, caseSensitive: false)
                ->orWhereHas('translations', $named)
                ->orWhereHas('parent.translations', $named)
                ->when($include !== [], fn (Builder $query) => $query->orWhereKey($include)))
            ->orderByRaw('coalesce(parent_id, id), parent_id nulls first, id')
            ->limit($limit)
            ->get();
    }

    public function variantsWithValue(int $valueId): array
    {
        // The pivot keeps the value's id in `attribute_id` (Product::attributeValues).
        return DB::table(config('items.table_prefix').'product_attribute_value')
            ->where('attribute_id', $valueId)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function containing(array $productIds, bool $percentOnly = false): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = $this->model->getTable();
        $parents = $this->query()->withTrashed()->toBase()->whereIn('id', $productIds)->whereNotNull('parent_id')->pluck('parent_id')->all();

        return BundleComponent::query()
            ->whereIn('product_id', array_values(array_unique([...$productIds, ...$parents])))
            ->when($percentOnly, fn (Builder $query) => $query->whereIn('bundle_id', fn ($bundles) => $bundles
                ->select('id')->from($products)->whereNotNull('bundle_discount_percent')))
            ->distinct()
            ->orderBy('bundle_id')
            ->pluck('bundle_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
