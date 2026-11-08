<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Order;

interface OrderRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query with what its rows show.
     */
    public function tableQuery(): Builder;

    /**
     * An order with what its form shows.
     */
    public function findForForm(int $id): Order;

    /**
     * An order locked for the rest of the transaction, with its items: two people changing its status
     * at once must each check the transition against what the other saved.
     */
    public function lockForUpdate(int $id): Order;
}
