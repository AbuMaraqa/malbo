<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Repositories\Contracts\StockMovementRepositoryInterface;

class StockMovementRepository extends BaseEloquentRepository implements StockMovementRepositoryInterface
{
    public function __construct(StockMovement $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with([
            'product.translations',
            'product.parent' => fn ($parent) => $parent->withTrashed(),
            'product.parent.translations',
            'order' => fn ($order) => $order->withTrashed(),
            'createdBy',
        ]);
    }
}
