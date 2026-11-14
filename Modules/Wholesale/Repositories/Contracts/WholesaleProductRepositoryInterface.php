<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Items\Entities\Product;

/**
 * The wholesale side's reads over Items products. A product is sold wholesale to a tier when it, or
 * one of its active variants, has a wholesale price above 0 or a price of its own in that tier.
 */
interface WholesaleProductRepositoryInterface
{
    /**
     * The portal's catalog, 24 a page, newest first: published, active parents sold wholesale to
     * this tier (null: no tier). Filters: q (name in any locale), category (slug, its children too).
     *
     * @param  array{q?: ?string, category?: ?string}  $filters
     */
    public function catalog(?int $tierId, array $filters, int $perPage = 24): LengthAwarePaginator;

    /**
     * The catalog as the PDF prints it: the same products and order, in a category (slug, its
     * children too; null: all), at most $limit.
     *
     * @return Collection<int, Product>
     */
    public function forCatalogPdf(?int $tierId, ?string $category, int $limit): Collection;

    /**
     * A published, active parent (or product without variants) with what the portal's product page
     * and the cart's size-run rules need: its active variations, their values and attributes.
     */
    public function findPublishedBySlug(string $slug): ?Product;

    /**
     * As findPublishedBySlug, by id; null when it is not on sale any more.
     */
    public function findPublished(int $id): ?Product;

    /**
     * These products (parents or products without variants) as findPublished loads them; the ones
     * not on sale are left out. Keyed by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public function publishedTrees(array $ids): Collection;

    /**
     * The ids of a product and all its variations, deleted and inactive ones too.
     *
     * @return list<int>
     */
    public function familyIds(int $productId): array;

    /**
     * The admin's wholesale-prices table: parent products with their translations, categories and
     * the number of variations.
     */
    public function adminTableQuery(): Builder;

    /**
     * A parent product (not a variation) and its variations that are not deleted, with their option
     * values, for the admin's wholesale-prices form.
     */
    public function forPriceEdit(int $id): Product;

    /**
     * Sets whether a product (a parent, or one without variants) may be ordered by the piece.
     */
    public function setByPiece(int $productId, bool $byPiece): void;
}
