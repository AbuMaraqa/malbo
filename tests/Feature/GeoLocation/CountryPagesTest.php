<?php

namespace Tests\Feature\GeoLocation;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Services\CountryService;
use Tests\TenantTestCase;

class CountryPagesTest extends TenantTestCase
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
            ->get($this->tenantUrl('/ar/admin/geolocation/countries'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/Country/Index')
                ->has('table.data.0.currency')
                ->has('codes')
                ->has('phoneCodes')
                ->has('currencies')
                ->where('locales', ['ar', 'en'])
                ->where('table.meta.per_page', 25));
    }

    public function test_search_finds_a_code_and_a_name_in_another_letter_case(): void
    {
        $this->assertSearchFinds('ps', 'PS');
        $this->assertSearchFinds('PALESTINE', null);
        app()->setLocale('ar');

        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/geolocation/countries?filter[name]=PALESTINE'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.code', 'PS'));
    }

    public function test_each_filter_narrows_the_list(): void
    {
        $palestine = Country::query()->where('code', 'PS')->firstOrFail();

        foreach ([
            'code' => 'PS',
            'phone_code' => '+970',
        ] as $filter => $value) {
            $this->assertFilterFinds("filter[{$filter}]=".urlencode($value), [$palestine->id]);
        }

        $expected = Country::query()->where('currency_id', $palestine->currency_id)->pluck('id')->sort()->values()->all();
        $this->assertFilterFinds('filter[currency_id]='.$palestine->currency_id, $expected);

        $service = app(CountryService::class);
        $onlyEnglish = $service->create([...$this->payload(['code' => 'ZZ']), 'name' => 'English only'], 'en');
        $this->assertFilterContains('filter[locale]=en', $onlyEnglish->id);
        $this->assertFilterExcludes('filter[locale]=ar', $onlyEnglish->id);

        Country::query()->whereKey($onlyEnglish->id)->update(['created_at' => '2020-01-15 10:00:00']);
        $this->assertFilterFinds('filter[created_at_to]=2020-02-01', [$onlyEnglish->id]);
        $this->assertFilterExcludes('filter[created_at_from]=2020-02-01', $onlyEnglish->id);
    }

    public function test_bad_filter_values_are_ignored_not_a_server_error(): void
    {
        foreach (['filter[currency_id]=abc', 'filter[created_at_from]=not-a-date'] as $query) {
            $this->actingAs($this->staff)->get($this->tenantUrl('/ar/admin/geolocation/countries?'.$query))->assertOk();
        }
    }

    public function test_sorting_by_the_translated_name(): void
    {
        $response = $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/geolocation/countries?sort=name&perPage=100'))
            ->assertOk();

        $names = array_column($response->viewData('page')['props']['table']['data'], 'name');

        $this->assertCount(Country::query()->count(), $names);

        $reversed = $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/geolocation/countries?sort=-name&perPage=100'))
            ->viewData('page')['props']['table']['data'];

        $this->assertSame(array_reverse($names), array_column($reversed, 'name'));
    }

    public function test_adding_a_country_stores_the_translation_of_the_current_locale_only(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/geolocation/countries'), $this->payload())
            ->assertRedirect(route('countries.index'))
            ->assertSessionHas('success');

        $country = Country::query()->where('code', 'TS')->firstOrFail();

        $this->assertSame('دولة', $country->translate('ar', false)?->name);
        $this->assertNull($country->translate('en', false));
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/geolocation/countries'), $this->payload(['name' => '', 'code' => 'PS', 'phone_code' => '', 'currency_id' => 999999]))
            ->assertSessionHasErrors(['name', 'code', 'phone_code', 'currency_id']);

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/geolocation/countries'), $this->payload(['code' => 'TOOLONG']))
            ->assertSessionHasErrors('code');
    }

    public function test_the_edit_page_sends_only_the_form_fields(): void
    {
        $country = Country::query()->where('code', 'PS')->firstOrFail();

        $this->actingAs($this->staff)
            ->get($this->tenantUrl("/ar/admin/geolocation/countries/{$country->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GeoLocation/Country/Edit')
                ->where('country', [
                    'id' => $country->id,
                    'name' => 'فلسطين',
                    'code' => 'PS',
                    'phone_code' => '+970',
                    'currency_id' => $country->currency_id,
                ]));
    }

    public function test_editing_updates_the_same_row_and_keeps_its_own_code(): void
    {
        $country = Country::query()->where('code', 'PS')->firstOrFail();
        $count = Country::query()->count();

        $this->actingAs($this->staff)
            ->put($this->tenantUrl("/ar/admin/geolocation/countries/{$country->id}"), [
                'name' => 'اسم جديد',
                'code' => 'PS',
                'phone_code' => '+971',
                'currency_id' => $country->currency_id,
            ])
            ->assertRedirect(route('countries.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($count, Country::query()->count());

        $fresh = $country->fresh();
        $this->assertSame('+971', $fresh->phone_code);
        $this->assertSame('اسم جديد', $fresh->translate('ar', false)?->name);
        $this->assertSame('Palestine', $fresh->translate('en', false)?->name);
    }

    public function test_deleting_a_country_with_nothing_attached(): void
    {
        $country = app(CountryService::class)->create($this->payload(), 'ar');

        $this->actingAs($this->staff)
            ->delete($this->tenantUrl('/ar/admin/geolocation/countries/'.$country->id))
            ->assertSessionHas('success');

        $this->assertNull(Country::query()->find($country->id));
    }

    public function test_a_country_with_governorates_is_not_deleted(): void
    {
        $country = Governorate::query()->firstOrFail()->country;

        $this->actingAs($this->staff)
            ->from($this->tenantUrl('/ar/admin/geolocation/countries'))
            ->delete($this->tenantUrl('/ar/admin/geolocation/countries/'.$country->id))
            ->assertSessionHas('error');

        $this->assertNotNull($country->fresh());
    }

    public function test_a_country_with_a_deleted_branch_is_not_deleted(): void
    {
        $country = app(CountryService::class)->create($this->payload(), 'ar');
        $branch = Branch::factory()->create(['country_id' => $country->id]);
        $branch->delete();

        $this->actingAs($this->staff)
            ->from($this->tenantUrl('/ar/admin/geolocation/countries'))
            ->delete($this->tenantUrl('/ar/admin/geolocation/countries/'.$country->id))
            ->assertSessionHas('error');

        $this->assertNotNull($country->fresh());
    }

    public function test_deleting_many_deletes_none_when_one_is_in_use(): void
    {
        $free = app(CountryService::class)->create($this->payload(), 'ar');
        $used = Governorate::query()->firstOrFail()->country_id;

        // The rollback of the service must not be mistaken for the test's own transaction.
        $this->actingAs($this->staff)
            ->from($this->tenantUrl('/ar/admin/geolocation/countries'))
            ->delete($this->tenantUrl('/ar/admin/geolocation/countries'), ['ids' => [$free->id, $used]])
            ->assertSessionHas('error');

        $this->assertNotNull($free->fresh());

        $this->actingAs($this->staff)
            ->delete($this->tenantUrl('/ar/admin/geolocation/countries'), ['ids' => [$free->id]])
            ->assertSessionHas('success');

        $this->assertNull($free->fresh());
    }

    public function test_the_routes_need_their_permissions(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('Country View List');

        $country = Country::query()->where('code', 'PS')->firstOrFail();

        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/geolocation/countries'))->assertOk();
        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/geolocation/countries/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->tenantUrl('/ar/admin/geolocation/countries'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->tenantUrl("/ar/admin/geolocation/countries/{$country->id}/edit"))->assertForbidden();
        $this->actingAs($viewer)->put($this->tenantUrl("/ar/admin/geolocation/countries/{$country->id}"), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl("/ar/admin/geolocation/countries/{$country->id}"))->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/geolocation/countries'), ['ids' => [$country->id]])->assertForbidden();

        $this->assertNotNull($country->fresh());
    }

    public function test_a_non_numeric_id_is_a_not_found(): void
    {
        $this->actingAs($this->staff)->get($this->tenantUrl('/ar/admin/geolocation/countries/abc/edit'))->assertNotFound();
    }

    public function test_a_request_field_called_name_no_longer_reaches_a_new_country(): void
    {
        request()->merge(['name' => 'from the request']);

        $country = new Country(['code' => 'QQ', 'phone_code' => '+1', 'currency_id' => Currency::query()->value('id')]);
        $country->save();

        $this->assertCount(0, $country->fresh()->translations);
    }

    private function assertSearchFinds(string $term, ?string $code): void
    {
        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/geolocation/countries?filter[search]='.$term))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($code): void {
                $page->where('table.meta.total', 1);

                if ($code !== null) {
                    $page->where('table.data.0.code', $code);
                }
            });
    }

    /**
     * @param  list<int>  $ids
     */
    private function assertFilterFinds(string $query, array $ids): void
    {
        $this->assertSame($ids, $this->idsFor($query));
    }

    private function assertFilterContains(string $query, int $id): void
    {
        $this->assertContains($id, $this->idsFor($query));
    }

    private function assertFilterExcludes(string $query, int $id): void
    {
        $this->assertNotContains($id, $this->idsFor($query));
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $query): array
    {
        $response = $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/geolocation/countries?perPage=100&sort=id&'.$query))
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
            'name' => 'دولة',
            'code' => 'TS',
            'phone_code' => '+999',
            'currency_id' => Currency::query()->value('id'),
            ...$overrides,
        ];
    }
}
