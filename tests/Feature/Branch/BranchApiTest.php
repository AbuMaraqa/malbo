<?php

namespace Tests\Feature\Branch;

use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Enums\WeekDay;
use Modules\Branch\Services\BranchService;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Tests\DatabaseTestCase;

class BranchApiTest extends DatabaseTestCase
{
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');

        $city = City::query()->with('governorate')->orderBy('id')->firstOrFail();

        $this->branch = app(BranchService::class)->create([
            'name' => 'Downtown',
            'address' => 'Main street',
            'description' => null,
            'country_id' => $city->governorate->country_id,
            'city_id' => $city->id,
            'status' => BranchStatus::OPEN->value,
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'working_hours' => [
                ['day' => WeekDay::SATURDAY->value, 'is_closed' => false, 'start_time' => '09:30', 'end_time' => '18:00'],
            ],
        ], 'ar', $this->staff()->id);
    }

    public function test_the_list_and_filters(): void
    {
        $this->apiGet('/api/v1/branches')->assertOk()->assertJsonPath('data.0.name', 'Downtown');
        $this->apiGet('/api/v1/branches?filter[name]=DOWN')->assertOk()->assertJsonCount(1, 'data');
        $this->apiGet('/api/v1/branches?filter[name]=nothing')->assertOk()->assertJsonCount(0, 'data');
        $this->apiGet('/api/v1/branches?filter[address]=main')->assertOk()->assertJsonCount(1, 'data');
        $this->apiGet('/api/v1/branches?filter[status]='.BranchStatus::CLOSED->value)->assertOk()->assertJsonCount(0, 'data');
        $this->apiGet('/api/v1/branches?filter[city_id]='.$this->branch->city_id)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_sorts_includes_and_bad_values_are_not_a_server_error(): void
    {
        foreach (['sort=name', 'sort=-name', 'sort=status', 'sort=id', 'filter[id]=abc', 'filter[status]=abc', 'filter[latitude]=abc', 'per_page=-3', 'include=workingHours,translations'] as $query) {
            $this->apiGet('/api/v1/branches?'.$query)->assertOk();
        }
    }

    public function test_show_finds_the_branch_with_its_hours(): void
    {
        $this->apiGet('/api/v1/branches/'.$this->branch->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->branch->id)
            ->assertJsonPath('data.name', 'Downtown')
            ->assertJsonPath('data.working_hours.0.start_time', '09:30')
            ->assertJsonPath('data.working_hours.0.end_time', '18:00');

        $this->apiGet('/api/v1/branches/abc')->assertNotFound();
        $this->apiGet('/api/v1/branches/99999999')->assertNotFound();
    }

    public function test_sync_returns_what_changed_since_and_needs_a_token(): void
    {
        $this->apiGet('/api/v1/branches/sync/branches')->assertUnauthorized();

        Sanctum::actingAs($this->staff()->givePermissionTo('Branch View List'));

        $this->apiGet('/api/v1/branches/sync/branches')->assertOk()->assertJsonCount(1, 'data');
        $this->apiGet('/api/v1/branches/sync/branches?since=2999-01-01')->assertOk()->assertJsonCount(0, 'data');
        $this->apiGet('/api/v1/branches/sync/branches?since=garbage')->assertUnprocessable();
    }

    private function staff(): User
    {
        return User::query()->where('email', 'staff@example.com')->first() ?? User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ]);
    }

    private function apiGet(string $path): TestResponse
    {
        return $this->getJson($this->siteUrl($path), ['Accept-Language' => 'ar']);
    }
}
