<?php

namespace Tests\Feature\GeoLocation;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Enums\CapitalType;
use Modules\GeoLocation\Enums\CityType;
use Modules\GeoLocation\Services\CityService;
use Tests\TenantTestCase;

class CityPagesTest extends TenantTestCase
{
    private const BASE = '/ar/admin/geolocation/cities';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');
    }

    public function test_the_list_is_shaped_for_the_table(): void
    {
        $this->actingAs($this->staff)
            ->get($this->tenantUrl(self::BASE))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/City/Index')
                ->has('table.data.0.governorate')
                ->has('table.data.0.country')
                ->has('countries.0.label')
                ->has('governorates.0.country_id')
                ->has('types', 4)
                ->has('capitalTypes', 3));
    }

    public function test_search_and_name_filter_ignore_the_letter_case(): void
    {
        $city = $this->city(['name' => 'Northern Town'], 'en');

        $this->assertContains($city->id, $this->idsFor('filter[search]=NORTHERN'));
        $this->assertSame([$city->id], $this->idsFor('filter[name]=northern'));
    }

    public function test_the_parent_filters_narrow_the_list(): void
    {
        $governorate = $this->emptyGovernorate();
        $city = $this->city(['governorate_id' => $governorate->id]);

        $this->assertSame([$city->id], $this->idsFor('filter[governorate_id]='.$governorate->id));
        $this->assertContains($city->id, $this->idsFor('filter[country_id]='.$governorate->country_id));

        $other = Governorate::query()->where('country_id', '!=', $governorate->country_id)->value('country_id');
        $this->assertNotContains($city->id, $this->idsFor('filter[country_id]='.($other ?? 999999)));
    }

    public function test_type_capital_population_locale_and_date_filters(): void
    {
        $city = $this->city([
            'type' => CityType::REFUGEE_CAMP->value,
            'is_capital' => true,
            'capital_type' => CapitalType::BOTH->value,
            'population' => 123456789,
        ], 'en');

        $this->assertContains($city->id, $this->idsFor('filter[type]='.CityType::REFUGEE_CAMP->value));
        $this->assertNotContains($city->id, $this->idsFor('filter[type]='.CityType::CITY->value));
        $this->assertContains($city->id, $this->idsFor('filter[is_capital]=1'));
        $this->assertNotContains($city->id, $this->idsFor('filter[is_capital]=0'));
        $this->assertContains($city->id, $this->idsFor('filter[capital_type]='.CapitalType::BOTH->value));
        $this->assertNotContains($city->id, $this->idsFor('filter[capital_type]='.CapitalType::COUNTRY->value));
        $this->assertSame([$city->id], $this->idsFor('filter[population_from]=123456789'));
        $this->assertNotContains($city->id, $this->idsFor('filter[population_to]=1000'));
        $this->assertContains($city->id, $this->idsFor('filter[locale]=en'));
        $this->assertNotContains($city->id, $this->idsFor('filter[locale]=ar'));

        City::query()->whereKey($city->id)->update(['created_at' => '2020-01-15 10:00:00']);
        $this->assertSame([$city->id], $this->idsFor('filter[created_at_to]=2020-02-01'));
    }

    public function test_bad_filter_values_are_ignored_not_a_server_error(): void
    {
        foreach ([
            'filter[type]=abc',
            'filter[type]=99999999999999',
            'filter[capital_type]=x',
            'filter[country_id]=abc',
            'filter[governorate_id]=abc',
            'filter[population_from]=1.5',
            'filter[population_from]=99999999999999',
            'filter[population_to]=abc',
            'filter[created_at_from]=nope',
        ] as $query) {
            $this->actingAs($this->staff)->get($this->tenantUrl(self::BASE.'?'.$query))->assertOk();
        }
    }

    public function test_sorting_by_name_population_and_type(): void
    {
        foreach (['name', '-name', 'population', '-population', 'type'] as $sort) {
            $this->actingAs($this->staff)->get($this->tenantUrl(self::BASE.'?sort='.$sort))->assertOk();
        }
    }

    public function test_adding_a_city_without_the_optional_fields(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl(self::BASE), [
                'name' => 'مدينة',
                'country_id' => 1, // only narrows the select, never stored
                'governorate_id' => Governorate::query()->value('id'),
                'population' => null,
                'latitude' => null,
                'longitude' => null,
                'type' => CityType::TOWN->value,
                'is_capital' => false,
                'capital_type' => null,
            ])
            ->assertRedirect(route('cities.index'))
            ->assertSessionHas('success');

        $city = City::query()->whereTranslation('name', 'مدينة')->firstOrFail();

        $this->assertSame(CityType::TOWN, $city->type);
        $this->assertNull($city->population);
        $this->assertNull($city->latitude);
        $this->assertNull($city->translate('en', false));
    }

    public function test_coordinates_may_be_negative(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl(self::BASE), $this->payload(['latitude' => -33.86, 'longitude' => -151.2]))
            ->assertSessionHasNoErrors();
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl(self::BASE), [
                'name' => '',
                'governorate_id' => 999999,
                'population' => -1,
                'latitude' => 91,
                'longitude' => 181,
                'type' => 99,
                'is_capital' => true,
                'capital_type' => null,
            ])
            ->assertSessionHasErrors(['name', 'governorate_id', 'population', 'latitude', 'longitude', 'type', 'capital_type']);
    }

    public function test_a_capital_type_is_kept_on_a_capital_only(): void
    {
        $city = $this->city(['is_capital' => true, 'capital_type' => CapitalType::GOVERNORATE->value]);
        $this->assertSame(CapitalType::GOVERNORATE, $city->fresh()->capital_type);

        $this->actingAs($this->staff)
            ->put($this->tenantUrl(self::BASE.'/'.$city->id), $this->payload(['is_capital' => false, 'capital_type' => CapitalType::GOVERNORATE->value]))
            ->assertSessionHasNoErrors();

        $this->assertNull($city->fresh()->capital_type);
    }

    public function test_the_labels_of_the_capital_types(): void
    {
        $this->assertSame(__('Country'), CapitalType::COUNTRY->label());
    }

    public function test_the_edit_page_and_updating_the_same_row(): void
    {
        $city = $this->city(['population' => 10]);
        $count = City::query()->count();

        $this->actingAs($this->staff)
            ->get($this->tenantUrl(self::BASE."/{$city->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/City/Edit')
                ->where('city.id', $city->id)
                ->where('city.name', 'مدينة')
                ->where('city.governorate_id', $city->governorate_id)
                ->where('city.country_id', $city->governorate->country_id)
                ->where('city.type', CityType::CITY->value)
                ->where('city.population', 10)
                ->has('governorates')
                ->has('capitalTypes'));

        $this->actingAs($this->staff)
            ->put($this->tenantUrl(self::BASE.'/'.$city->id), $this->payload(['name' => 'جديد', 'population' => 20]))
            ->assertRedirect(route('cities.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($count, City::query()->count());
        $this->assertSame(20, $city->fresh()->population);
        $this->assertSame('جديد', $city->fresh()->translate('ar', false)?->name);
    }

    public function test_deleting_a_free_city_and_refusing_one_in_use(): void
    {
        $free = $this->city();
        $withDistrict = District::query()->firstOrFail()->city;
        $withBranch = $this->city();
        $branch = Branch::factory()->create(['city_id' => $withBranch->id]);
        $branch->delete();

        foreach ([$withDistrict, $withBranch] as $used) {
            $this->actingAs($this->staff)
                ->from($this->tenantUrl(self::BASE))
                ->delete($this->tenantUrl(self::BASE.'/'.$used->id))
                ->assertSessionHas('error');
            $this->assertNotNull($used->fresh());
        }

        $this->actingAs($this->staff)
            ->from($this->tenantUrl(self::BASE))
            ->delete($this->tenantUrl(self::BASE), ['ids' => [$free->id, $withDistrict->id]])
            ->assertSessionHas('error');
        $this->assertNotNull($free->fresh());

        $this->actingAs($this->staff)
            ->delete($this->tenantUrl(self::BASE.'/'.$free->id))
            ->assertSessionHas('success');
        $this->assertNull($free->fresh());
    }

    public function test_the_routes_need_their_permissions(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('City View List');

        $city = City::query()->firstOrFail();

        $this->actingAs($viewer)->get($this->tenantUrl(self::BASE))->assertOk();
        $this->actingAs($viewer)->get($this->tenantUrl(self::BASE.'/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->tenantUrl(self::BASE), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->tenantUrl(self::BASE."/{$city->id}/edit"))->assertForbidden();
        $this->actingAs($viewer)->put($this->tenantUrl(self::BASE."/{$city->id}"), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl(self::BASE."/{$city->id}"))->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl(self::BASE), ['ids' => [$city->id]])->assertForbidden();
    }

    public function test_a_non_numeric_id_is_a_not_found(): void
    {
        $this->actingAs($this->staff)->get($this->tenantUrl(self::BASE.'/abc/edit'))->assertNotFound();
    }

    private function emptyGovernorate(): Governorate
    {
        $governorate = new Governorate(['code' => 'EMPTY', 'country_id' => Governorate::query()->value('country_id')]);
        $governorate->save();

        return $governorate;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function city(array $overrides = [], string $locale = 'ar'): City
    {
        return app(CityService::class)->create($this->payload($overrides), $locale);
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $query): array
    {
        $response = $this->actingAs($this->staff)
            ->get($this->tenantUrl(self::BASE.'?perPage=100&sort=id&'.$query))
            ->assertOk();

        return array_column($response->viewData('page')['props']['table']['data'], 'id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'مدينة',
            'governorate_id' => Governorate::query()->value('id'),
            'population' => null,
            'latitude' => null,
            'longitude' => null,
            'type' => CityType::CITY->value,
            'is_capital' => false,
            'capital_type' => null,
            ...$overrides,
        ];
    }
}
