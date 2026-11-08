<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Order;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;

class OrderRepository extends BaseEloquentRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['user', 'branch.translations', 'items.product.translations']);
    }

    public function findForForm(int $id): Order
    {
        /** @var Order */
        return $this->query()
            ->with(['user', 'items' => fn ($items) => $items->orderBy('id'), 'items.product.translations', 'items.addonOptions'])
            ->findOrFail($id);
    }

    public function lockForUpdate(int $id): Order
    {
        /** @var Order */
        return $this->query()->lockForUpdate()->with(['items.addonOptions'])->findOrFail($id);
    }
}
