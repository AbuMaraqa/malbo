<?php

namespace Modules\GeoLocation\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;

class CityRepository extends BaseEloquentRepository implements CityRepositoryInterface
{
    public function __construct(City $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'governorate.translations', 'governorate.country.translations']);
    }

    public function options(): array
    {
        return $this->query()->with(['translations', 'governorate'])->get()
            ->map(fn (City $city) => [
                'value' => $city->id,
                'label' => (string) $city->name,
                'governorate_id' => $city->governorate_id,
                'country_id' => $city->governorate?->country_id,
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    public function isInCountry(int $cityId, int $countryId): bool
    {
        return $this->query()
            ->whereKey($cityId)
            ->whereHas('governorate', fn (Builder $query) => $query->where('country_id', $countryId))
            ->exists();
    }
}
