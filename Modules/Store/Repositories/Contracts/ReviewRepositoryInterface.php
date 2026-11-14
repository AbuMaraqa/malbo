<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\Review;

interface ReviewRepositoryInterface extends RepositoryInterface
{
    /**
     * The newest store (B2C) order of this account, delivered, holding the product or one of its
     * variants: what makes the account a verified buyer. Wholesale orders do not count.
     */
    public function eligibleOrderId(int $userId, int $productId): ?int;

    /**
     * The account's review of this product, locked for the rest of the transaction.
     */
    public function lockForUser(int $userId, int $productId): ?Review;

    public function findForUser(int $userId, int $productId): ?Review;

    /**
     * Approved reviews of a product, newest first, with their author and photos.
     */
    public function approvedForProduct(int $productId, int $perPage, string $pageName): LengthAwarePaginator;

    /**
     * @return array{average: ?float, count: int} over the approved reviews
     */
    public function summary(int $productId): array;

    /**
     * The account's reviews, newest first, with their product and photos.
     *
     * @return Collection<int, Review>
     */
    public function forUser(int $userId): Collection;

    /**
     * The moderation table: every review with its product, author and photos.
     */
    public function tableQuery(): Builder;

    public function findForModeration(int $id): ?Review;

    /**
     * Products that have reviews, for the table's filter.
     *
     * @return list<array{value: int, label: string}>
     */
    public function reviewedProducts(): array;
}
