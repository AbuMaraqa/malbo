<?php

namespace Tests\Feature\GeoLocation;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Enums\DistrictType;
use Modules\GeoLocation\Services\DistrictService;
use Tests\DatabaseTestCase;

class DistrictPagesTest extends DatabaseTestCase
{
    private const BASE = '/ar/admin/geolocation/districts';

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
            ->get($this->siteUrl(self::BASE))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/District/Index')
                ->has('table.data.0.city')
                ->has('table.data.0.governorate')
                ->has('governorates.0.country_id')
                ->has('cities.0.governorate_id')
                ->has('types', 4));
    }

    public function test_search_and_name_filter_ignore_the_letter_case(): void
    {
        $district = $this->district(['name' => 'Old Harbour'], 'en');

        $this->assertContains($district->id, $this->idsFor('filter[search]=HARBOUR'));
        $this->assertSame([$district->id], $this->idsFor('filter[name]=harbour'));
    }

    public function test_the_parent_filters_narrow_the_list(): void
    {
        $city = City::query()->whereDoesntHave('districts')->with('governorate')->firstOrFail();
        $district = $this->district(['city_id' => $city->id]);

        $this->assertSame([$district->id], $this->idsFor('filter[city_id]='.$city->id));
        $this->assertContains($district->id, $this->idsFor('filter[governorate_id]='.$city->governorate_id));
        $this->assertContains($district->id, $this->idsFor('filter[country_id]='.$city->governorate->country_id));
        $this->assertNotContains($district->id, $this->idsFor('filter[city_id]='.City::query()->whereKeyNot($city->id)->value('id')));
        $this->assertNotContains($district->id, $this->idsFor('filter[country_id]=999999'));
    }

    public function test_type_locale_and_date_filters(): void
    {
        $district = $this->district(['type' => DistrictType::QUARTER->value], 'en');

        $this->assertContains($district->id, $this->idsFor('filter[type]='.DistrictType::QUARTER->value));
        $this->assertNotContains($district->id, $this->idsFor('filter[type]='.DistrictType::SUBURB->value));
        $this->assertContains($district->id, $this->idsFor('filter[locale]=en'));
        $this->assertNotContains($district->id, $this->idsFor('filter[locale]=ar'));

        District::query()->whereKey($district->id)->update(['created_at' => '2020-01-15 10:00:00']);
        $this->assertSame([$district->id], $this->idsFor('filter[created_at_to]=2020-02-01'));
    }

    public function test_bad_filter_values_are_ignored_not_a_server_error(): void
    {
        foreach (['filter[type]=abc', 'filter[city_id]=abc', 'filter[governorate_id]=abc', 'filter[country_id]=abc', 'filter[created_at_from]=nope'] as $query) {
            $this->actingAs($this->staff)->get($this->siteUrl(self::BASE.'?'.$query))->assertOk();
        }
    }

    public function test_the_type_labels_name_the_district_types(): void
    {
        $this->assertSame(__('District'), DistrictType::DISTRICT->label());
        $this->assertSame(__('Neighborhood'), DistrictType::NEIGHBORHOOD->label());
        $this->assertSame(__('Suburb'), DistrictType::SUBURB->label());
        $this->assertSame(__('Quarter'), DistrictType::QUARTER->label());
    }

    public function test_adding_a_district(): void
    {
        $this->actingAs($this->staff)
            ->post($this->siteUrl(self::BASE), $this->payload(['country_id' => 1, 'governorate_id' => 1, 'latitude' => 31.5]))
            ->assertRedirect(route('districts.index'))
            ->assertSessionHas('success');

        $district = District::query()->whereTranslation('name', 'منطقة')->firstOrFail();

        $this->assertSame(DistrictType::NEIGHBORHOOD, $district->type);
        $this->assertSame('31.50000000', $district->latitude);
        $this->assertNull($district->longitude);
        $this->assertNull($district->translate('en', false));
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->staff)
            ->post($this->siteUrl(self::BASE), ['name' => '', 'city_id' => 999999, 'type' => 99, 'latitude' => 'x', 'longitude' => 200])
            ->assertSessionHasErrors(['name', 'city_id', 'type', 'latitude', 'longitude']);
    }

    public function test_the_edit_page_and_updating_the_same_row(): void
    {
        $district = $this->district();
        $count = District::query()->count();

        $this->actingAs($this->staff)
            ->get($this->siteUrl(self::BASE."/{$district->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/District/Edit')
                ->where('district.id', $district->id)
                ->where('district.name', 'منطقة')
                ->where('district.city_id', $district->city_id)
                ->where('district.governorate_id', $district->city->governorate_id)
                ->where('district.country_id', $district->city->governorate->country_id)
                ->where('district.type', DistrictType::NEIGHBORHOOD->value));

        $this->actingAs($this->staff)
            ->put($this->siteUrl(self::BASE.'/'.$district->id), $this->payload(['name' => 'جديد', 'type' => DistrictType::SUBURB->value]))
            ->assertRedirect(route('districts.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($count, District::query()->count());
        $this->assertSame(DistrictType::SUBURB, $district->fresh()->type);
        $this->assertSame('جديد', $district->fresh()->translate('ar', false)?->name);
    }

    public function test_deleting_takes_the_translations_with_it(): void
    {
        $one = $this->district();
        $two = $this->district();

        $this->actingAs($this->staff)
            ->delete($this->siteUrl(self::BASE.'/'.$one->id))
            ->assertSessionHas('success');
        $this->assertNull($one->fresh());

        $this->actingAs($this->staff)
            ->delete($this->siteUrl(self::BASE), ['ids' => [$two->id]])
            ->assertSessionHas('success');
        $this->assertNull($two->fresh());
    }

    public function test_the_routes_need_their_permissions(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('District View List');

        $district = District::query()->firstOrFail();

        $this->actingAs($viewer)->get($this->siteUrl(self::BASE))->assertOk();
        $this->actingAs($viewer)->get($this->siteUrl(self::BASE.'/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl(self::BASE), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl(self::BASE."/{$district->id}/edit"))->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl(self::BASE."/{$district->id}"), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->siteUrl(self::BASE."/{$district->id}"))->assertForbidden();
        $this->actingAs($viewer)->delete($this->siteUrl(self::BASE), ['ids' => [$district->id]])->assertForbidden();
    }

    public function test_a_non_numeric_id_is_a_not_found(): void
    {
        $this->actingAs($this->staff)->get($this->siteUrl(self::BASE.'/abc/edit'))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function district(array $overrides = [], string $locale = 'ar'): District
    {
        return app(DistrictService::class)->create($this->payload($overrides), $locale);
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $query): array
    {
        $response = $this->actingAs($this->staff)
            ->get($this->siteUrl(self::BASE.'?perPage=100&sort=id&'.$query))
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
            'name' => 'منطقة',
            'city_id' => City::query()->value('id'),
            'type' => DistrictType::NEIGHBORHOOD->value,
            'latitude' => null,
            'longitude' => null,
            ...$overrides,
        ];
    }
}
