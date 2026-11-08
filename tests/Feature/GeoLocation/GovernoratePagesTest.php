<?php

namespace Tests\Feature\GeoLocation;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Services\GovernorateService;
use Tests\DatabaseTestCase;

class GovernoratePagesTest extends DatabaseTestCase
{
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
            ->get($this->siteUrl('/ar/admin/geolocation/governorates'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/Governorate/Index')
                ->has('table.data.0.country')
                ->has('codes')
                ->has('countries.0.label'));
    }

    public function test_search_finds_a_code_and_a_name_in_another_letter_case(): void
    {
        $governorate = app(GovernorateService::class)->create($this->payload(['name' => 'Northern Valley', 'code' => 'NVX']), 'en');

        foreach (['nvx', 'NORTHERN'] as $term) {
            $this->assertContains($governorate->id, $this->idsFor('filter[search]='.$term));
        }

        $this->assertSame([$governorate->id], $this->idsFor('filter[name]=northern'));
    }

    public function test_each_filter_narrows_the_list(): void
    {
        $service = app(GovernorateService::class);
        $country = Country::query()->whereDoesntHave('governorates')->firstOrFail();
        $governorate = $service->create($this->payload(['country_id' => $country->id]), 'en');

        $this->assertSame([$governorate->id], $this->idsFor('filter[country_id]='.$country->id));
        $this->assertSame([$governorate->id], $this->idsFor('filter[code]=TST'));
        $this->assertContains($governorate->id, $this->idsFor('filter[locale]=en'));
        $this->assertNotContains($governorate->id, $this->idsFor('filter[locale]=ar'));

        Governorate::query()->whereKey($governorate->id)->update(['created_at' => '2020-01-15 10:00:00']);
        $this->assertSame([$governorate->id], $this->idsFor('filter[created_at_to]=2020-02-01'));
        $this->assertNotContains($governorate->id, $this->idsFor('filter[created_at_from]=2020-02-01'));
    }

    public function test_bad_filter_values_are_ignored_not_a_server_error(): void
    {
        foreach (['filter[country_id]=abc', 'filter[created_at_to]=nope'] as $query) {
            $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/geolocation/governorates?'.$query))->assertOk();
        }
    }

    public function test_adding_a_governorate(): void
    {
        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/geolocation/governorates'), $this->payload())
            ->assertRedirect(route('governorates.index'))
            ->assertSessionHas('success');

        $governorate = Governorate::query()->where('code', 'TST')->firstOrFail();

        $this->assertSame('محافظة', $governorate->translate('ar', false)?->name);
        $this->assertNull($governorate->translate('en', false));
    }

    public function test_validation_errors(): void
    {
        $taken = Governorate::query()->whereNotNull('code')->value('code');

        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/geolocation/governorates'), ['name' => '', 'code' => $taken, 'country_id' => null])
            ->assertSessionHasErrors(['name', 'code', 'country_id']);

        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/geolocation/governorates'), $this->payload(['code' => 'MORE-THAN-TEN', 'country_id' => 999999]))
            ->assertSessionHasErrors(['code', 'country_id']);
    }

    public function test_the_edit_page_and_updating_the_same_row(): void
    {
        $governorate = Governorate::query()->whereNotNull('code')->firstOrFail();
        $count = Governorate::query()->count();

        $this->actingAs($this->staff)
            ->get($this->siteUrl("/ar/admin/geolocation/governorates/{$governorate->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/Governorate/Edit')
                ->where('governorate.id', $governorate->id)
                ->where('governorate.code', $governorate->code)
                ->where('governorate.country_id', $governorate->country_id)
                ->has('countries'));

        $this->actingAs($this->staff)
            ->put($this->siteUrl("/ar/admin/geolocation/governorates/{$governorate->id}"), [
                'name' => 'اسم جديد',
                'code' => $governorate->code,
                'country_id' => $governorate->country_id,
            ])
            ->assertRedirect(route('governorates.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($count, Governorate::query()->count());
        $this->assertSame('اسم جديد', $governorate->fresh()->translate('ar', false)?->name);
    }

    public function test_deleting_a_free_governorate_and_refusing_one_with_cities(): void
    {
        $free = app(GovernorateService::class)->create($this->payload(), 'ar');
        $used = City::query()->firstOrFail()->governorate;

        $this->actingAs($this->staff)
            ->from($this->siteUrl('/ar/admin/geolocation/governorates'))
            ->delete($this->siteUrl('/ar/admin/geolocation/governorates/'.$used->id))
            ->assertSessionHas('error');
        $this->assertNotNull($used->fresh());

        $this->actingAs($this->staff)
            ->from($this->siteUrl('/ar/admin/geolocation/governorates'))
            ->delete($this->siteUrl('/ar/admin/geolocation/governorates'), ['ids' => [$free->id, $used->id]])
            ->assertSessionHas('error');
        $this->assertNotNull($free->fresh());

        $this->actingAs($this->staff)
            ->delete($this->siteUrl('/ar/admin/geolocation/governorates/'.$free->id))
            ->assertSessionHas('success');
        $this->assertNull($free->fresh());
    }

    public function test_the_routes_need_their_permissions(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('Governorate View List');

        $governorate = Governorate::query()->firstOrFail();
        $base = '/ar/admin/geolocation/governorates';

        $this->actingAs($viewer)->get($this->siteUrl($base))->assertOk();
        $this->actingAs($viewer)->get($this->siteUrl($base.'/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl($base), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl("{$base}/{$governorate->id}/edit"))->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl("{$base}/{$governorate->id}"), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->siteUrl("{$base}/{$governorate->id}"))->assertForbidden();
        $this->actingAs($viewer)->delete($this->siteUrl($base), ['ids' => [$governorate->id]])->assertForbidden();
    }

    public function test_a_non_numeric_id_is_a_not_found(): void
    {
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/geolocation/governorates/abc/edit'))->assertNotFound();
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $query): array
    {
        $response = $this->actingAs($this->staff)
            ->get($this->siteUrl('/ar/admin/geolocation/governorates?perPage=100&sort=id&'.$query))
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
            'name' => 'محافظة',
            'code' => 'TST',
            'country_id' => Country::query()->value('id'),
            ...$overrides,
        ];
    }
}
