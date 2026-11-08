<?php

namespace Modules\GeoLocation\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;

class CountryRepository extends BaseEloquentRepository implements CountryRepositoryInterface
{
    public function __construct(Country $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'currency.translations']);
    }

    public function options(): array
    {
        return $this->query()->with('translations')->get()
            ->map(fn (Country $country) => ['value' => $country->id, 'label' => (string) $country->name])
            ->sortBy('label')
            ->values()
            ->all();
    }

    public function distinctValues(string $column): array
    {
        return $this->query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->all();
    }
}
