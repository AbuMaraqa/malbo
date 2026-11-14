<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\Store\Entities\ShippingZone;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;

class ShippingZoneRepository extends BaseEloquentRepository implements ShippingZoneRepositoryInterface
{
    public function __construct(ShippingZone $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('translations')->withCount('cities');
    }

    public function forEdit(int $id): ShippingZone
    {
        /** @var ShippingZone */
        return $this->query()->with(['translations', 'cities'])->findOrFail($id);
    }

    public function activeForCity(int $cityId): ?ShippingZone
    {
        /** @var ShippingZone|null */
        return $this->query()->with('translations')
            ->where('is_active', true)
            ->whereHas('cities', fn (Builder $cities) => $cities->whereKey($cityId))
            ->orderBy('sort_order')->orderBy('id')
            ->first();
    }

    public function takenCityIds(array $cityIds, ?int $exceptZoneId = null): array
    {
        if ($cityIds === []) {
            return [];
        }

        $pivot = config('store.table_prefix').'shipping_zone_city';
        $zones = $this->model->getTable();

        return $this->query()->toBase()
            ->join($pivot, "{$pivot}.shipping_zone_id", '=', "{$zones}.id")
            ->where("{$zones}.is_active", true)
            ->when($exceptZoneId, fn ($query) => $query->where("{$zones}.id", '!=', $exceptZoneId))
            ->whereIn("{$pivot}.city_id", $cityIds)
            ->distinct()
            ->pluck("{$pivot}.city_id")
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function deliverableCities(): array
    {
        $zones = $this->query()->where('is_active', true)
            ->with(['cities.translations', 'cities.districts.translations'])
            ->get();

        return $zones->flatMap(fn (ShippingZone $zone) => $zone->cities)
            ->unique('id')
            ->map(fn (City $city) => [
                'value' => $city->id,
                'label' => (string) $city->name,
                'districts' => $city->districts
                    ->map(fn (District $district) => ['value' => $district->id, 'label' => (string) $district->name])
                    ->sortBy('label')->values()->all(),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }
}
