<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\StockAlert;

interface StockAlertRepositoryInterface extends RepositoryInterface
{
    /**
     * Waiting alerts of these items, in id order, read in chunks.
     *
     * @param  list<int>  $productIds
     * @return LazyCollection<int, StockAlert>
     */
    public function waitingFor(array $productIds): LazyCollection;

    /**
     * Waiting alerts held by this email or this phone (already normalised).
     */
    public function waitingCount(?string $email, ?string $phone): int;

    /**
     * One row per item for the admin: waiting and notified counts, the latest request, the item with
     * its parent and values.
     */
    public function groupedByProductQuery(): Builder;
}
