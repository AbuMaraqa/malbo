<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

/**
 * The stock ledger, read only: InventoryService writes it.
 */
interface StockMovementRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query with what its rows show (the product and its parent, deleted ones
     * too; the order, deleted too; who made the change).
     */
    public function tableQuery(): Builder;
}
