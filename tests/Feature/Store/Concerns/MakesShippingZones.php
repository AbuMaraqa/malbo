<?php

namespace Tests\Feature\Store\Concerns;

use Modules\Core\Entities\Currency;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Store\Entities\ShippingZone;

/**
 * Cities of a test country, and shipping zones over them.
 */
trait MakesShippingZones
{
    private ?Governorate $testGovernorate = null;

    protected function city(string $name = 'مدينة', array $districts = []): City
    {
        if ($this->testGovernorate === null) {
            $currency = Currency::query()->firstOrCreate(['code' => 'ILS'], ['name' => 'شيكل', 'symbol' => '₪']);
            $country = Country::query()->firstOrCreate(['code' => 'ZZ'], ['name' => 'بلد', 'phone_code' => '970', 'currency_id' => $currency->id]);
            $this->testGovernorate = Governorate::query()->create(['name' => 'محافظة', 'country_id' => $country->id]);
        }

        $city = City::query()->create(['name' => $name, 'governorate_id' => $this->testGovernorate->id]);

        foreach ($districts as $district) {
            District::query()->create(['name' => $district, 'city_id' => $city->id]);
        }

        return $city;
    }

    /**
     * @param  list<City>  $cities
     */
    protected function zone(array $cities = [], array $attributes = []): ShippingZone
    {
        $zone = ShippingZone::query()->create([
            'fee' => 20,
            'eta_min_days' => 1,
            'eta_max_days' => 3,
            'free_shipping_threshold' => null,
            'is_active' => true,
            'sort_order' => 0,
            ...$attributes,
        ]);
        $zone->translateOrNew('ar')->name = $attributes['name'] ?? 'منطقة';
        $zone->save();
        $zone->cities()->sync(array_map(fn (City $city) => $city->id, $cities));

        return $zone;
    }
}
