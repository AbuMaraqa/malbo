<?php

namespace Modules\GeoLocation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface CityRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every city with its governorate, country and translations.
     */
    public function tableQuery(): Builder;

    /**
     * Every city as a select option that knows its parents, sorted by name.
     *
     * @return list<array{value: int, label: string, governorate_id: int, country_id: int|null}>
     */
    public function options(): array;

    /**
     * Whether the city lies in the country.
     */
    public function isInCountry(int $cityId, int $countryId): bool;
}
