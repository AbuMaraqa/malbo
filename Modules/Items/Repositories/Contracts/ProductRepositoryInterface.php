<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Product;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin products table: parent products with the table's eager loads.
     */
    public function tableQuery(): Builder;

    /**
     * What a storefront may show: parent products that are Published, active and not deleted.
     */
    public function publishedForStorefront(): Builder;

    public function findBySlug(string $slug): ?Product;

    /**
     * The shop grid, 24 a page. Filters: category (slug, its child categories included), q (name in
     * any locale), sort (newest|price_asc|price_desc|featured; anything else is newest).
     *
     * @param  array{category?: ?string, q?: ?string, sort?: ?string}  $filters
     */
    public function storefrontListing(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Featured storefront products, topped up with the newest when there are fewer than $limit.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function featuredForStorefront(int $limit = 8): \Illuminate\Database\Eloquent\Collection;

    /**
     * A storefront product page's product with its variations, addons and categories, or null
     * when it is not on the storefront (draft, inactive, deleted, or a variation).
     */
    public function findPublishedBySlug(string $slug): ?Product;

    /**
     * Slug and last change of every storefront product, for the sitemap.
     *
     * @return \Illuminate\Support\Collection<int, object{slug: string, updated_at: ?string}>
     */
    public function storefrontSlugs(): \Illuminate\Support\Collection;

    /**
     * Products sharing a category with $product, topped up with the newest; never $product itself.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function relatedForStorefront(Product $product, int $limit = 4): \Illuminate\Database\Eloquent\Collection;

    /**
     * $slug made URL-safe and unique among all products, deleted ones included (the column is
     * unique); `-1`, `-2`... is appended when taken. $ignoreId is the product being edited.
     */
    public function uniqueSlug(string $slug, ?int $ignoreId = null): string;

    /**
     * A product with everything the Edit page shows.
     */
    public function forEdit(int|string $id): Product;

    /**
     * A product and its variations, with their names.
     */
    public function withVariations(int|string $id): Product;

    /**
     * Saved branch prices of these products, keyed `productId:branchId`.
     *
     * @param  iterable<int>  $productIds
     * @return \Illuminate\Support\Collection<string, \Modules\Items\Entities\BranchPrice>
     */
    public function branchPrices(iterable $productIds): \Illuminate\Support\Collection;

    /**
     * Parent products for the offer form: `{value, label, branches}` (the branches it is sold in).
     *
     * @return list<array{value: int, label: string, branches: list<int>}>
     */
    public function parentOptionsForOffers(): array;

    /**
     * Whether a parent product is sold in at least one of these branches.
     *
     * @param  list<int>  $branchIds
     */
    public function isParentInBranches(int $productId, array $branchIds): bool;

    /**
     * Parent products by name or sku in any letter case, plus the ones in $include (already chosen).
     *
     * @param  list<int>  $include
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function searchParents(string $term, array $include = [], int $limit = 50): \Illuminate\Database\Eloquent\Collection;

    /**
     * A deleted variation of $parent with this slug, which can be restored instead of created.
     */
    public function trashedVariation(Product $parent, string $slug): ?Product;

    /**
     * Per-product option overrides (addon_product_option rows), grouped by addon_product id.
     *
     * @param  iterable<int>  $addonProductIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, object>>
     */
    public function addonOptionOverrides(iterable $addonProductIds): \Illuminate\Support\Collection;

    /**
     * Attribute value ids used by the (not deleted) variations of $product. The pivot column is
     * named attribute_id but holds attribute value ids.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function variationAttributeValueIds(Product $product): \Illuminate\Support\Collection;

    /**
     * Products updated after $since with what the app's sync sends, oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function changedSince(string $since): \Illuminate\Database\Eloquent\Collection;
}
