<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\ReturnRequest;

interface ReturnRequestRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query with what its rows show.
     */
    public function tableQuery(): Builder;

    /**
     * A request with its order, lines, products and history (the admin's detail page).
     */
    public function findForForm(int $id): ReturnRequest;

    /**
     * The request locked for the rest of the transaction, with its lines and their order lines.
     */
    public function lockForUpdate(int $id): ReturnRequest;

    /**
     * An order's requests, the newest first, with their lines and history.
     *
     * @return Collection<int, ReturnRequest>
     */
    public function forOrder(int $orderId): Collection;

    public function hasOpen(int $orderId): bool;

    /**
     * The refunds recorded on the order's return requests that still count (not rejected or
     * cancelled), leaving one request out.
     */
    public function refundedSoFar(int $orderId, ?int $exceptId = null): float;

    public function hasCompletedExchange(int $orderId): bool;

    /**
     * Units of each order line already taken by the order's requests that were not rejected or cancelled
     * (open or completed), or only by the completed ones.
     *
     * @return array<int, int> order item id => quantity
     */
    public function takenQuantities(int $orderId, bool $completedOnly = false): array;
}
