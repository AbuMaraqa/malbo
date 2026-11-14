<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Wholesale\Entities\WholesaleCart;

interface WholesaleCartRepositoryInterface extends RepositoryInterface
{
    /**
     * The user's open cart (not ordered yet), with its lines.
     */
    public function findOpenForUser(int $userId): ?WholesaleCart;

    /**
     * The cart locked for the rest of the transaction, with its lines.
     */
    public function lock(int $id): ?WholesaleCart;

    /**
     * The user's cart that became this order, if any.
     */
    public function findForOrder(int $userId, int $orderId): ?WholesaleCart;
}
