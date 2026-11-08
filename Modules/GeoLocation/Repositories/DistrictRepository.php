<?php

namespace Modules\GeoLocation\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;

class DistrictRepository extends BaseEloquentRepository implements DistrictRepositoryInterface
{
    public function __construct(District $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'city.translations', 'city.governorate.translations']);
    }
}
