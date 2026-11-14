<?php

namespace Modules\Wholesale\Repositories;

use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Repositories\Contracts\WholesaleCartRepositoryInterface;

class WholesaleCartRepository extends BaseEloquentRepository implements WholesaleCartRepositoryInterface
{
    public function __construct(WholesaleCart $model)
    {
        parent::__construct($model);
    }

    public function findOpenForUser(int $userId): ?WholesaleCart
    {
        /** @var WholesaleCart|null */
        // Open: never converted (a converted cart whose order was deleted has no order_id left).
        return $this->query()->with('items')->where('user_id', $userId)->whereNull('order_id')->whereNull('converted_at')->first();
    }

    public function lock(int $id): ?WholesaleCart
    {
        /** @var WholesaleCart|null */
        return $this->query()->lockForUpdate()->find($id)?->load('items');
    }

    public function findForOrder(int $userId, int $orderId): ?WholesaleCart
    {
        /** @var WholesaleCart|null */
        return $this->query()->where('user_id', $userId)->where('order_id', $orderId)->first();
    }
}
