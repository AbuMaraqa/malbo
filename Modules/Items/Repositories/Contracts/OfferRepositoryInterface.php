<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Offer;

interface OfferRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query with what its rows show.
     */
    public function tableQuery(): Builder;

    /**
     * An offer with what its form shows.
     */
    public function findForForm(int $id): Offer;
}
