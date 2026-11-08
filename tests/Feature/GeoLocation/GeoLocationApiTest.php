<?php

namespace Tests\Feature\GeoLocation;

use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Tests\TenantTestCase;

class GeoLocationApiTest extends TenantTestCase
{
    public function test_the_lists_answer_with_translated_names(): void
    {
        foreach (['countries', 'governorates', 'cities', 'districts'] as $list) {
            $response = $this->apiGet("/api/v1/geolocation/{$list}?per_page=100")->assertOk();

            $this->assertNotEmpty($response->json('data'));
            $this->assertNotNull($response->json('data.0.name'), $list);
        }
    }

    public function test_the_arabic_header_changes_the_language(): void
    {
        $country = Country::query()->where('code', 'PS')->firstOrFail();

        $this->apiGet('/api/v1/geolocation/countries/'.$country->id, 'ar')->assertOk()->assertJsonPath('data.name', 'فلسطين');
        $this->apiGet('/api/v1/geolocation/countries/'.$country->id, 'en')->assertOk()->assertJsonPath('data.name', 'Palestine');
    }

    public function test_show_finds_each_entity_and_a_bad_id_is_a_not_found(): void
    {
        foreach ([
            'countries' => Country::class,
            'governorates' => Governorate::class,
            'cities' => City::class,
            'districts' => District::class,
        ] as $list => $model) {
            $id = $model::query()->value('id');

            $this->apiGet("/api/v1/geolocation/{$list}/{$id}")->assertOk()->assertJsonPath('data.id', $id);
            $this->apiGet("/api/v1/geolocation/{$list}/abc")->assertNotFound();
            $this->apiGet("/api/v1/geolocation/{$list}/99999999")->assertNotFound();
        }
    }

    public function test_the_name_filter_and_sort_work_on_the_translated_name(): void
    {
        foreach (['countries', 'governorates', 'cities', 'districts'] as $list) {
            $this->apiGet("/api/v1/geolocation/{$list}?sort=name")->assertOk();
            $this->apiGet("/api/v1/geolocation/{$list}?sort=-name")->assertOk();
            $this->apiGet("/api/v1/geolocation/{$list}?filter[name]=a")->assertOk();
        }

        $this->apiGet('/api/v1/geolocation/countries?filter[name]=PALESTINE', 'en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PS');
    }

    public function test_the_parent_filters(): void
    {
        $city = District::query()->firstOrFail()->city;
        $governorate = $city->governorate;

        foreach ($this->apiGet('/api/v1/geolocation/governorates?per_page=100&filter[country_id]='.$governorate->country_id)->json('data') as $row) {
            $this->assertSame($governorate->country_id, $row['country_id']);
        }

        foreach ($this->apiGet('/api/v1/geolocation/cities?per_page=100&filter[governorate_id]='.$governorate->id)->json('data') as $row) {
            $this->assertSame($governorate->id, $row['governorate_id']);
        }

        $this->assertNotEmpty($this->apiGet('/api/v1/geolocation/cities?filter[country_id]='.$governorate->country_id)->json('data'));
        $this->assertEmpty($this->apiGet('/api/v1/geolocation/cities?filter[country_id]=99999999')->json('data'));

        foreach ($this->apiGet('/api/v1/geolocation/districts?per_page=100&filter[city_id]='.$city->id)->json('data') as $row) {
            $this->assertSame($city->id, $row['city_id']);
        }

        $this->assertNotEmpty($this->apiGet('/api/v1/geolocation/districts?filter[governorate_id]='.$governorate->id)->json('data'));
    }

    public function test_includes_load_their_translations(): void
    {
        $this->apiGet('/api/v1/geolocation/countries?include=currency')->assertOk()->assertJsonStructure(['data' => [['currency']]]);
        $this->apiGet('/api/v1/geolocation/governorates?include=country,cities')->assertOk()->assertJsonStructure(['data' => [['country', 'cities']]]);
        $this->apiGet('/api/v1/geolocation/cities?include=governorate')->assertOk()->assertJsonStructure(['data' => [['governorate']]]);
        $this->apiGet('/api/v1/geolocation/districts?include=city,governorate')->assertOk()->assertJsonStructure(['data' => [['city', 'governorate']]]);

        $country = Country::query()->value('id');
        $this->apiGet("/api/v1/geolocation/countries/{$country}?include=currency")->assertOk()->assertJsonStructure(['data' => ['currency']]);
    }

    public function test_bad_values_are_not_a_server_error(): void
    {
        foreach ([
            '/api/v1/geolocation/countries?filter[id]=abc',
            '/api/v1/geolocation/countries?per_page=-5',
            '/api/v1/geolocation/countries?per_page=abc',
            '/api/v1/geolocation/governorates?filter[country_id]=abc',
            '/api/v1/geolocation/cities?filter[type]=abc',
            '/api/v1/geolocation/cities?filter[is_capital]=1',
            '/api/v1/geolocation/cities?filter[latitude]=abc',
            '/api/v1/geolocation/cities?filter[country_id]=abc',
            '/api/v1/geolocation/districts?filter[governorate_id]=abc',
        ] as $url) {
            $this->apiGet($url)->assertOk();
        }
    }

    private function apiGet(string $path, string $locale = 'ar'): \Illuminate\Testing\TestResponse
    {
        return $this->getJson($this->tenantUrl($path), ['Accept-Language' => $locale]);
    }
}
