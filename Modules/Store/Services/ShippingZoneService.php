<?php

namespace Modules\Store\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Support\Translations;
use Modules\Store\Entities\ShippingZone;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Settings\StoreShippingSettings;

/**
 * Writes shipping zones: their numbers, the name in one locale and their cities, together. A city
 * may belong to one active zone only; that is checked here, against what is saved, every time a
 * zone becomes active or its cities change.
 */
class ShippingZoneService
{
    private const TRANSLATED = ['name'];

    public function __construct(private readonly ShippingZoneRepositoryInterface $zones) {}

    /**
     * @param  array<string, mixed>  $data  name, fee, eta_min_days, eta_max_days, free_shipping_threshold, is_active, sort_order, cities
     */
    public function create(array $data, string $locale, int $userId): ShippingZone
    {
        return DB::transaction(function () use ($data, $locale, $userId): ShippingZone {
            /** @var ShippingZone $zone */
            $zone = $this->zones->query()->newModelInstance();
            $zone->fill([...self::columns($data), 'created_by' => $userId]);
            Translations::fill($zone, Translations::fromForm($data, $locale, self::TRANSLATED));

            $this->assertCitiesFree($zone, $data['cities'] ?? []);

            $zone->save();
            $zone->cities()->sync(self::ids($data['cities'] ?? []));

            return $zone;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShippingZone $zone, array $data, string $locale): ShippingZone
    {
        return DB::transaction(function () use ($zone, $data, $locale): ShippingZone {
            $zone->fill(self::columns($data));
            Translations::fill($zone, Translations::fromForm($data, $locale, self::TRANSLATED));

            $this->assertCitiesFree($zone, $data['cities'] ?? []);

            $zone->save();
            $zone->cities()->sync(self::ids($data['cities'] ?? []));

            return $zone;
        });
    }

    /**
     * @throws ValidationException when a city of the zone is in another active zone
     */
    public function setActive(ShippingZone $zone, bool $active): ShippingZone
    {
        return DB::transaction(function () use ($zone, $active): ShippingZone {
            $zone->is_active = $active;
            $cities = $zone->cities()->pluck($zone->cities()->getRelated()->getQualifiedKeyName())->all();
            $this->assertCitiesFree($zone, $cities, 'active');
            $zone->save();

            return $zone;
        });
    }

    public function delete(ShippingZone $zone): void
    {
        $this->zones->delete($zone);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->zones->query()->whereKey($ids)->get()->each(
            fn (ShippingZone $zone) => $this->zones->delete($zone)
        ));
    }

    public function saveSettings(?float $defaultThreshold): void
    {
        $settings = app(StoreShippingSettings::class);
        $settings->default_free_shipping_threshold = $defaultThreshold === null ? null : round($defaultThreshold, 2);
        $settings->save();
    }

    /**
     * @param  list<int|string>  $cityIds
     *
     * @throws ValidationException
     */
    private function assertCitiesFree(ShippingZone $zone, array $cityIds, string $field = 'cities'): void
    {
        if (! $zone->is_active) {
            return;
        }

        // Two zones saved at once over the same city: the second waits, then sees the first.
        DB::statement('LOCK TABLE '.$zone->getTable().' IN SHARE ROW EXCLUSIVE MODE');

        if ($this->zones->takenCityIds(self::ids($cityIds), $zone->id) !== []) {
            throw ValidationException::withMessages([$field => __('Some of these cities already belong to another active zone.')]);
        }
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return [
            'fee' => round((float) $data['fee'], 2),
            'eta_min_days' => (int) $data['eta_min_days'],
            'eta_max_days' => (int) $data['eta_max_days'],
            'free_shipping_threshold' => filled($data['free_shipping_threshold'] ?? null) ? round((float) $data['free_shipping_threshold'], 2) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
