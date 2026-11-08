<?php

namespace Tests\Feature\Branch;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Enums\WeekDay;
use Modules\Branch\Services\BranchService;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Tests\DatabaseTestCase;

class BranchPagesTest extends DatabaseTestCase
{
    private const BASE = '/ar/admin/branch/appointment';

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
        $this->branch();

        $this->actingAs($this->staff)
            ->get($this->siteUrl(self::BASE))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branch/Branch/Index')
                ->where('table.data.0.name', 'فرع')
                ->where('table.data.0.opening_time', '08:00')
                ->where('table.data.0.status', BranchStatus::OPEN->value)
                ->has('table.data.0.open_days', 6)
                ->has('statuses', 2)
                ->has('countries.0.label')
                ->has('cities.0.country_id'));
    }

    public function test_search_and_text_filters_ignore_the_letter_case(): void
    {
        $branch = $this->branch(['name' => 'Downtown Branch', 'email' => 'Main@Store.test', 'phone' => '0599123456'], 'en');
        $this->branch(['email' => 'other@store.test', 'phone' => '0599000000']);

        foreach (['filter[search]=DOWNTOWN', 'filter[search]=main@store', 'filter[search]=599123', 'filter[name]=downtown', 'filter[email]=MAIN@', 'filter[phone]=599123'] as $query) {
            $this->assertSame([$branch->id], $this->idsFor($query), $query);
        }
    }

    public function test_status_location_locale_and_date_filters(): void
    {
        $country = $this->cityWithCountry()->governorate->country_id;
        $city = $this->cityWithCountry()->id;
        $closed = $this->branch(['status' => BranchStatus::CLOSED->value], 'en');
        $open = $this->branch();

        $this->assertSame([$closed->id], $this->idsFor('filter[status]='.BranchStatus::CLOSED->value));
        $this->assertSame([$open->id], $this->idsFor('filter[status]='.BranchStatus::OPEN->value));
        $this->assertSame([$closed->id, $open->id], $this->idsFor('filter[country_id]='.$country));
        $this->assertSame([], $this->idsFor('filter[country_id]=99999999'));
        $this->assertSame([$closed->id, $open->id], $this->idsFor('filter[city_id]='.$city));
        $this->assertSame([], $this->idsFor('filter[city_id]=99999999'));
        $this->assertSame([$closed->id], $this->idsFor('filter[locale]=en'));
        $this->assertSame([$open->id], $this->idsFor('filter[locale]=ar'));

        Branch::query()->whereKey($closed->id)->update(['created_at' => '2020-01-15 10:00:00']);
        $this->assertSame([$closed->id], $this->idsFor('filter[created_at_to]=2020-02-01'));
        $this->assertSame([$open->id], $this->idsFor('filter[created_at_from]=2020-02-01'));
    }

    public function test_bad_filter_values_are_ignored_and_sorts_work(): void
    {
        foreach (['filter[status]=abc', 'filter[country_id]=abc', 'filter[city_id]=abc', 'filter[created_at_from]=nope', 'sort=name', 'sort=-name', 'sort=status', 'sort=opening_time', 'sort=closing_time'] as $query) {
            $this->actingAs($this->staff)->get($this->siteUrl(self::BASE.'?'.$query))->assertOk();
        }
    }

    public function test_the_add_page_offers_seven_days(): void
    {
        $this->actingAs($this->staff)
            ->get($this->siteUrl(self::BASE.'/add'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branch/Branch/Add')
                ->has('workingHours', 7)
                ->where('workingHours.0.start_time', '08:00')
                ->has('days', 7));
    }

    public function test_adding_a_branch_with_its_hours_and_translation(): void
    {
        $this->actingAs($this->staff)
            ->post($this->siteUrl(self::BASE), $this->payload())
            ->assertRedirect(route('branches.index'))
            ->assertSessionHas('success');

        $branch = Branch::query()->with('workingHours')->latest('id')->firstOrFail();

        $this->assertSame($this->staff->id, $branch->created_by);
        $this->assertSame('فرع', $branch->translate('ar', false)?->name);
        $this->assertSame('عنوان', $branch->translate('ar', false)?->address);
        $this->assertNull($branch->translate('en', false));
        $this->assertSame('31.90000000', $branch->latitude);
        $this->assertSame('35.20000000', $branch->longitude);
        $this->assertCount(7, $branch->workingHours);

        $friday = $branch->workingHours->first(fn ($hour) => $hour->day === WeekDay::FRIDAY);
        $this->assertTrue($friday->is_closed);
        $this->assertNull($friday->start_time);

        $saturday = $branch->workingHours->first(fn ($hour) => $hour->day === WeekDay::SATURDAY);
        $this->assertFalse($saturday->is_closed);
        $this->assertSame('09:30', $saturday->start_time->format('H:i'));
        $this->assertSame('18:00', $saturday->end_time->format('H:i'));
    }

    public function test_a_request_field_called_name_no_longer_reaches_a_new_branch(): void
    {
        request()->merge(['name' => 'from the request', 'address' => 'from the request']);

        $branch = Branch::factory()->create(['name' => 'explicit', 'address' => 'explicit']);

        $this->assertSame('explicit', $branch->fresh()->name);
    }

    public function test_validation_errors(): void
    {
        $hours = $this->hours();
        $hours[0]['start_time'] = null;
        $hours[1]['end_time'] = '25:99';

        $this->actingAs($this->staff)
            ->post($this->siteUrl(self::BASE), $this->payload([
                'name' => '',
                'email' => 'not-an-email',
                'phone' => 'letters',
                'latitude' => 95,
                'opening_time' => 'noon',
                'country_id' => null,
                'city_id' => null,
                'status' => 9,
                'working_hours' => $hours,
            ]))
            ->assertSessionHasErrors(['name', 'email', 'phone', 'latitude', 'opening_time', 'country_id', 'city_id', 'status', 'working_hours.0.start_time', 'working_hours.1.end_time']);
    }

    public function test_a_phone_may_carry_a_plus_sign(): void
    {
        $this->actingAs($this->staff)
            ->post($this->siteUrl(self::BASE), $this->payload(['phone' => '+970 599 123456']))
            ->assertSessionHasNoErrors();
    }

    public function test_the_city_must_be_in_the_country(): void
    {
        $otherCountry = Country::query()->whereKeyNot($this->cityWithCountry()->governorate->country_id)->value('id');

        $this->actingAs($this->staff)
            ->post($this->siteUrl(self::BASE), $this->payload(['country_id' => $otherCountry]))
            ->assertSessionHasErrors('city_id');
    }

    public function test_the_edit_page_sends_the_form_and_fills_missing_days_from_the_branch(): void
    {
        $branch = $this->branch();
        $factoryBranch = Branch::factory()->create([
            'city_id' => $this->cityWithCountry()->id,
            'opening_time' => '10:00',
            'closing_time' => '20:00',
        ]);

        $this->actingAs($this->staff)
            ->get($this->siteUrl(self::BASE."/{$branch->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branch/Branch/Edit')
                ->where('branch.id', $branch->id)
                ->where('branch.name', 'فرع')
                ->where('branch.opening_time', '08:00')
                ->where('branch.country_id', $branch->country_id)
                ->where('branch.city_id', $branch->city_id)
                ->where('branch.status', BranchStatus::OPEN->value)
                ->has('branch.working_hours', 7)
                ->where('branch.working_hours.0.start_time', '09:30')
                ->where('branch.working_hours.6.is_closed', true)
                ->where('branch.working_hours.6.start_time', null)
                ->missing('branch.created_by'));

        $this->actingAs($this->staff)
            ->get($this->siteUrl(self::BASE."/{$factoryBranch->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branch.working_hours.0.start_time', '10:00')
                ->where('branch.working_hours.0.end_time', '20:00'));
    }

    public function test_editing_updates_the_same_row_and_its_hours(): void
    {
        $branch = $this->branch();
        $hours = $this->hours();
        $hours[0]['start_time'] = '11:00';
        $hours[1] = ['day' => $hours[1]['day'], 'is_closed' => true, 'start_time' => '09:00', 'end_time' => '10:00'];

        $this->actingAs($this->staff)
            ->put($this->siteUrl(self::BASE.'/'.$branch->id), $this->payload([
                'name' => 'جديد',
                'status' => BranchStatus::CLOSED->value,
                'working_hours' => $hours,
            ]))
            ->assertRedirect(route('branches.index'))
            ->assertSessionHasNoErrors();

        $fresh = Branch::query()->with('workingHours')->findOrFail($branch->id);

        $this->assertSame(1, Branch::query()->count());
        $this->assertSame('جديد', $fresh->translate('ar', false)?->name);
        $this->assertSame(BranchStatus::CLOSED, $fresh->status);
        $this->assertCount(7, $fresh->workingHours);
        $this->assertSame('11:00', $fresh->workingHours->first(fn ($hour) => $hour->day === WeekDay::SATURDAY)->start_time->format('H:i'));

        $sunday = $fresh->workingHours->first(fn ($hour) => $hour->day === WeekDay::SUNDAY);
        $this->assertTrue($sunday->is_closed);
        $this->assertNull($sunday->start_time);
    }

    public function test_editing_in_another_locale_keeps_the_first_translation(): void
    {
        $branch = $this->branch();

        $this->actingAs($this->staff)
            ->put($this->siteUrl('/en/admin/branch/appointment/'.$branch->id), $this->payload(['name' => 'English name']))
            ->assertSessionHasNoErrors();

        $fresh = $branch->fresh();
        $this->assertSame('فرع', $fresh->translate('ar', false)?->name);
        $this->assertSame('English name', $fresh->translate('en', false)?->name);
    }

    public function test_deleting_hides_the_branch_and_leaves_its_staff(): void
    {
        $one = $this->branch();
        $two = $this->branch();
        $this->staff->forceFill(['branch_id' => $one->id])->save();

        $this->actingAs($this->staff)
            ->delete($this->siteUrl(self::BASE.'/'.$one->id))
            ->assertSessionHas('success');

        $this->actingAs($this->staff)
            ->delete($this->siteUrl(self::BASE), ['ids' => [$two->id]])
            ->assertSessionHas('success');

        $this->assertSame(0, Branch::query()->count());
        $this->assertSame(2, Branch::query()->withTrashed()->count());
        $this->assertNotNull($this->staff->fresh());
    }

    public function test_the_routes_need_their_permissions(): void
    {
        $branch = $this->branch();

        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('Branch View List');

        $nobody = User::forceCreate([
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('Currency View List');

        $this->actingAs($nobody)->get($this->siteUrl(self::BASE))->assertForbidden();

        $this->actingAs($viewer)->get($this->siteUrl(self::BASE))->assertOk();
        $this->actingAs($viewer)->get($this->siteUrl(self::BASE.'/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl(self::BASE), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl(self::BASE."/{$branch->id}/edit"))->assertForbidden();
        $this->actingAs($viewer)->put($this->siteUrl(self::BASE."/{$branch->id}"), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->siteUrl(self::BASE."/{$branch->id}"))->assertForbidden();
        $this->actingAs($viewer)->delete($this->siteUrl(self::BASE), ['ids' => [$branch->id]])->assertForbidden();

        $this->assertNotNull($branch->fresh());
    }

    public function test_a_non_numeric_id_is_a_not_found(): void
    {
        $this->actingAs($this->staff)->get($this->siteUrl(self::BASE.'/abc/edit'))->assertNotFound();
    }

    public function test_the_sidebar_lists_branches_with_their_icon_for_those_allowed(): void
    {
        $find = fn () => collect(app(SidebarService::class)->all())->firstWhere('title', 'Branches');

        $this->actingAs($this->staff);
        $this->assertSame('branches', $find()['icon']);
        $this->assertSame('branches.index', $find()['children'][0]['route']);

        $other = User::forceCreate([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('Currency View List');

        $this->actingAs($other);
        $this->assertNull($find());
    }

    private function cityWithCountry(): City
    {
        return City::query()->with('governorate')->orderBy('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function branch(array $overrides = [], string $locale = 'ar'): Branch
    {
        return app(BranchService::class)->create($this->payload($overrides), $locale, $this->staff->id);
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
     * @return list<array<string, mixed>>
     */
    private function hours(): array
    {
        return array_map(fn (WeekDay $day) => [
            'day' => $day->value,
            'is_closed' => $day === WeekDay::FRIDAY,
            'start_time' => $day === WeekDay::FRIDAY ? null : '09:30',
            'end_time' => $day === WeekDay::FRIDAY ? null : '18:00',
        ], WeekDay::cases());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $city = $this->cityWithCountry();

        return [
            'name' => 'فرع',
            'description' => 'وصف',
            'address' => 'عنوان',
            'email' => 'branch@store.test',
            'phone' => '0599000000',
            'latitude' => 31.9,
            'longitude' => 35.2,
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'country_id' => $city->governorate->country_id,
            'city_id' => $city->id,
            'status' => BranchStatus::OPEN->value,
            'working_hours' => $this->hours(),
            ...$overrides,
        ];
    }
}
