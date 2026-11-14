<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Product;

/**
 * Sets and bundle offers: products with a `bundle_type`, and their components.
 */
interface BundleRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query: bundles only, with their names, image, categories and component count.
     */
    public function tableQuery(): Builder;

    /**
     * A bundle with what its form shows (texts, images, categories, components and their names).
     */
    public function forEdit(int $id): Product;

    /**
     * A bundle with its components loaded as BundleStock needs them.
     */
    public function withComponents(int $id): ?Product;

    /**
     * What a component may be, by name or SKU in any letter case: a product (the buyer picks its
     * variant), a variant (fixed), or a product without variants; never a bundle, never deleted.
     * $include keeps these ids in the list whatever the term.
     *
     * @param  list<int>  $include
     * @return Collection<int, Product>
     */
    public function componentOptions(string $term, array $include = [], int $limit = 50): Collection;

    /**
     * Ids of the products (variants) that hold this attribute value.
     *
     * @return list<int>
     */
    public function variantsWithValue(int $valueId): array;

    /**
     * Ids of the bundles one of whose components is one of these products, or the parent of one.
     *
     * @param  list<int>  $productIds
     * @param  bool  $percentOnly  only bundles priced as a percentage off their components
     * @return list<int>
     */
    public function containing(array $productIds, bool $percentOnly = false): array;
}
