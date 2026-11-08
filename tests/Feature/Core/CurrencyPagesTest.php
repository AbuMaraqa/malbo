<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Tests\TenantTestCase;

/**
 * The reference for admin page tests: list, filters, add, edit, delete, permissions.
 */
class CurrencyPagesTest extends TenantTestCase
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

    public function test_the_list_is_paginated_and_shaped_for_the_table(): void
    {
        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/currencies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Currency/Index')
                ->has('table.data')
                ->has('table.meta.total')
                ->has('table.query.sort')
                ->where('table.meta.per_page', 25));
    }

    public function test_search_finds_a_currency_in_another_letter_case(): void
    {
        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/currencies?filter[search]=usd'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.data.0.code', 'USD'));
    }

    public function test_the_active_filter_compares_a_boolean(): void
    {
        Currency::query()->where('code', 'USD')->update(['is_active' => false]);

        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/currencies?filter[is_active]=0'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.data.0.code', 'USD'));
    }

    public function test_a_malformed_date_filter_is_ignored_not_a_server_error(): void
    {
        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/currencies?filter[created_at_from]=not-a-date'))
            ->assertOk();
    }

    public function test_adding_a_currency_saves_its_name_in_the_current_language(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/currencies'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $currency = Currency::query()->where('code', 'TST')->firstOrFail();

        $this->assertSame('عملة', $currency->translate('ar')->name);
        $this->assertSame($this->staff->id, $currency->created_by);
    }

    public function test_editing_updates_the_same_currency_and_does_not_create_a_new_one(): void
    {
        $currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $count = Currency::query()->count();

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/currencies/'.$currency->id), $this->payload(['code' => 'USD', 'symbol' => 'US$']))
            ->assertRedirect();

        $this->assertSame($count, Currency::query()->count());
        $this->assertSame('US$', $currency->fresh()->symbol);
    }

    public function test_a_duplicate_code_is_a_validation_error(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/currencies'), $this->payload(['code' => 'USD']))
            ->assertSessionHasErrors('code');
    }

    public function test_a_non_numeric_id_is_a_not_found_not_a_server_error(): void
    {
        $this->actingAs($this->staff)
            ->get($this->tenantUrl('/ar/admin/currencies/abc/edit'))
            ->assertNotFound();
    }

    public function test_deleting_needs_the_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret-password',
        ])->givePermissionTo('Currency View List');

        $currency = Currency::query()->where('code', 'USD')->firstOrFail();

        $this->actingAs($viewer)
            ->delete($this->tenantUrl('/ar/admin/currencies/'.$currency->id))
            ->assertForbidden();

        $this->assertNotNull($currency->fresh());
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('/ar/admin/currencies'))->assertRedirect();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'عملة',
            'plural_name' => '',
            'code' => 'TST',
            'symbol' => 'T',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'exchange_rate' => 1.5,
            'is_active' => true,
            ...$overrides,
        ];
    }
}
