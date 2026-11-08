<?php

namespace Modules\Core\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\Currency;
use Modules\Core\Repositories\Contracts\CurrencyRepositoryInterface;

class CurrencyRepository extends BaseEloquentRepository implements CurrencyRepositoryInterface
{
    public function __construct(Currency $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('translations');
    }
}
