<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\ShippingZone;

interface ShippingZoneRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query: translations and the number of cities.
     */
    public function tableQuery(): Builder;

    /**
     * A zone with its translations and cities, for the edit page.
     */
    public function forEdit(int $id): ShippingZone;

    /**
     * The active zone that delivers to this city, or null (no delivery there).
     */
    public function activeForCity(int $cityId): ?ShippingZone;

    /**
     * Which of these cities already belong to another active zone.
     *
     * @param  list<int>  $cityIds
     * @return list<int>
     */
    public function takenCityIds(array $cityIds, ?int $exceptZoneId = null): array;

    /**
     * Cities an active zone delivers to, sorted by name, each with its districts.
     *
     * @return list<array{value: int, label: string, districts: list<array{value: int, label: string}>}>
     */
    public function deliverableCities(): array;
}
