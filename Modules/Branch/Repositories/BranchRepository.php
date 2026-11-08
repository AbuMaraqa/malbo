<?php

namespace Modules\Branch\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Repositories\BaseEloquentRepository;

class BranchRepository extends BaseEloquentRepository implements BranchRepositoryInterface
{
    public function __construct(Branch $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'workingHours', 'country.translations', 'city.translations']);
    }

    public function openForStorefront(): Collection
    {
        return $this->query()->with('translations')->where('status', BranchStatus::OPEN)->orderBy('id')->get();
    }

    public function changedSince(string $since): Collection
    {
        return $this->query()->with('translations')->where('updated_at', '>', $since)->get();
    }

    public function existsInCity(int $cityId): bool
    {
        return $this->query()->withTrashed()->where('city_id', $cityId)->exists();
    }

    public function existsInCountry(int $countryId): bool
    {
        return $this->query()->withTrashed()->where('country_id', $countryId)->exists();
    }
}
