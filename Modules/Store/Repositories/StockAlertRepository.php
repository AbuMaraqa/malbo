<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Repositories\Contracts\StockAlertRepositoryInterface;

class StockAlertRepository extends BaseEloquentRepository implements StockAlertRepositoryInterface
{
    public function __construct(StockAlert $model)
    {
        parent::__construct($model);
    }

    public function waitingFor(array $productIds): LazyCollection
    {
        return $this->query()->whereIn('product_id', $productIds)->whereNull('notified_at')->lazyById(100);
    }

    public function waitingCount(?string $email, ?string $phone): int
    {
        if (blank($email) && blank($phone)) {
            return 0;
        }

        return $this->query()->whereNull('notified_at')->where(function (Builder $query) use ($email, $phone): void {
            if (filled($email)) {
                $query->orWhere('email', $email);
            }

            if (filled($phone)) {
                $query->orWhere('phone', $phone);
            }
        })->count();
    }

    public function groupedByProductQuery(): Builder
    {
        return $this->query()
            ->select('product_id')
            ->selectRaw('count(*) filter (where notified_at is null) as waiting_count')
            ->selectRaw('count(*) filter (where notified_at is not null) as notified_count')
            ->selectRaw('max(created_at) as last_requested_at')
            ->with([
                'product' => fn ($product) => $product->withTrashed(),
                'product.translations',
                'product.parent' => fn ($parent) => $parent->withTrashed(),
                'product.parent.translations',
                'product.attributeValues.translations',
            ])
            ->groupBy('product_id');
    }
}
