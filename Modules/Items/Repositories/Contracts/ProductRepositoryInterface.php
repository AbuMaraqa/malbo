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
     * The shop grid, 24 a page. Filters, already cleaned (StorefrontShop):
     * - category: slug, its active child categories included; or category_ids: those ids, already
     *   resolved (the slug then only names the cache entry);
     * - q: a name in any locale, the product's or a variant's SKU, a tag or an active category name;
     *   exact then prefix name matches first;
     * - attributes: attribute id => value ids: one variant a buyer can see and order holds one of the
     *   values of every attribute (OR inside one attribute, AND across them);
     * - price_min, price_max: on the displayed price (PricingService, computed in SQL);
     * - sort: newest|best_selling|price_asc|price_desc|featured; anything else is newest.
     *
     * @param  array{category?: ?string, q?: ?string, attributes?: array<int, list<int>>, price_min?: ?float, price_max?: ?float, sort?: ?string}  $filters
     */
    public function storefrontListing(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * The shop's facets for these filters: how many products each value of these attributes would
     * show with the other filters (one grouped query), and the displayed price's bounds without the
     * price filter.
     *
     * @param  array<string, mixed>  $filters  as storefrontListing
     * @param  list<int>  $attributeIds  the filterable attributes
     * @return array{values: array<int, int>, price: array{min: ?float, max: ?float}}  values: value id => products
     */
    public function storefrontFacets(array $filters, array $attributeIds): array;

    /**
     * Featured storefront products, topped up with the newest when there are fewer than $limit.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function featuredForStorefront(int $limit = 8): \Illuminate\Database\Eloquent\Collection;

    /**
     * Published parents the shop's search finds (as storefrontListing's `q`), best matches first, then
     * the newest; with their translations and images.
     */
    public function searchSuggestions(string $term, int $limit = 6): \Illuminate\Database\Eloquent\Collection;

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
     * A product (or variant) by id for the product admin: never a set or bundle (404), which has its
     * own form and permissions.
     */
    public function findProductOrFail(int $id): Product;

    /**
     * Whether this id is a set or bundle.
     */
    public function isBundle(int $id): bool;

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
     * What can go on an order line: variants, and products that have none. Matched by sku, own
     * name or the parent's name, in any letter case, plus the ones in $include (already chosen).
     *
     * @param  list<int>  $include
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function searchSellable(string $term, array $include = [], int $limit = 50): \Illuminate\Database\Eloquent\Collection;

    /**
     * Parent products (not variations) whose name in any locale or sku holds $term, in any letter
     * case, newest first; drafts too. For pickers such as the collection form. Sets and bundles only
     * with $withBundles.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function searchParents(string $term, int $limit = 50, bool $withBundles = false): \Illuminate\Database\Eloquent\Collection;

    /**
     * "Complete the look" of a product as the admin edits it: its products in order, whatever their
     * status, with their names.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function lookOptions(Product $product): \Illuminate\Database\Eloquent\Collection;

    /**
     * "Complete the look" on the storefront: the published, active ones (never a set), in order,
     * with what a card shows and their variants' stock and values.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function completeTheLook(Product $product): \Illuminate\Database\Eloquent\Collection;

    /**
     * Whether the product can go on an order line and holds stock: a variant, or a product with
     * no variants (see searchSellable).
     */
    public function isSellable(int $id): bool;

    /**
     * Sellable products whose stock is managed and at or below their low-stock threshold, only
     * those for sale (published and active; a variant active with its parent published and
     * active): the low-stock page and the dashboard's count.
     */
    public function lowStockQuery(): Builder;

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

    /**
     * These products (deleted ones too), locked for a stock change, in id order so two changes
     * never wait on each other. Must run inside a transaction.
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function lockForStock(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * Where each product's sales count: its parent for a variant, else itself. Deleted rows included.
     *
     * @param  list<int>  $ids
     * @return array<int, int> product id => the id that counts its sales
     */
    public function countedProductIds(array $ids): array;

    /**
     * Products as cart lines need them, deleted ones too (a line is flagged, not lost): names,
     * images, option values, the parent (deleted too) with its addons, and the active variations'
     * ids (a product that has some is sold through them).
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function forCart(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * The storefront's products with these ids, as cards need them, in the order of $ids; ids the
     * storefront does not show are left out (a wishlist).
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function publishedByIds(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * The sellable variants of a parent (active, not deleted), with their option values: what a
     * buyer may take in exchange for one of them.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function siblingVariants(int $parentId): \Illuminate\Database\Eloquent\Collection;
}
