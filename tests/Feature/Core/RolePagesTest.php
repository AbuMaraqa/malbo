<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class RolePagesTest extends TenantTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    protected function tearDown(): void
    {
        // The permission cache must not outlive this test's rolled back rows.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_the_list_shows_roles_with_permission_groups_in_arabic(): void
    {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl('/ar/admin/roles?filter[search]=super'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Roles/Index')
                ->where('table.meta.total', 1)
                ->where('table.data.0.name', 'Super Admin')
                ->where('table.data.0.protected', true)
                ->where('table.data.0.editable', false)
                ->where('table.data.0.summary.total', Permission::query()->count())
                ->where('permissionGroups', fn ($groups) => collect($groups)->contains('label', 'المستخدمون'))
                ->has('permissionOptions'));
    }

    public function test_the_filters_narrow_the_list(): void
    {
        $permission = Permission::findByName('Currency View List', 'web');
        Role::create(['name' => 'Only Currency', 'guard_name' => 'web'])->givePermissionTo($permission);
        Role::create(['name' => 'Nothing', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->get($this->tenantUrl('/ar/admin/roles?filter[permission]='.$permission->id.'&filter[search]=only'))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.name', 'Only Currency'));

        $this->actingAs($this->admin)
            ->get($this->tenantUrl('/ar/admin/roles?filter[has_users]=0&filter[search]=nothing'))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1));

        $this->actingAs($this->admin)
            ->get($this->tenantUrl('/ar/admin/roles?filter[has_users]=1&filter[search]=nothing'))
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));

        $this->actingAs($this->admin)
            ->get($this->tenantUrl('/ar/admin/roles?filter[created_at_from]=2020-01-01&filter[created_at_to]=bad&sort=-users_count'))
            ->assertOk();
    }

    public function test_creating_a_role_saves_its_permissions_and_they_take_effect(): void
    {
        $permission = Permission::findByName('Currency View List', 'web');

        $this->actingAs($this->admin)
            ->post($this->tenantUrl('/ar/admin/roles'), ['name' => 'Cashier', 'permissions' => [$permission->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $role = Role::findByName('Cashier', 'web');
        $this->assertTrue($role->hasPermissionTo('Currency View List'));

        $cashier = User::forceCreate(['name' => 'C', 'email' => 'c@example.com', 'password' => 'secret-password'])->assignRole($role);

        $this->actingAs($cashier)->get($this->tenantUrl('/ar/admin/currencies'))->assertOk();
    }

    public function test_a_duplicate_name_and_a_bad_permission_are_validation_errors(): void
    {
        $this->actingAs($this->admin)
            ->post($this->tenantUrl('/ar/admin/roles'), ['name' => 'Super Admin', 'permissions' => ['abc', 99999999]])
            ->assertSessionHasErrors(['name', 'permissions.0', 'permissions.1']);
    }

    public function test_editing_updates_the_same_role_and_permission_changes_apply_at_once(): void
    {
        $role = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $role->givePermissionTo('Currency View List');
        $cashier = User::forceCreate(['name' => 'C', 'email' => 'c@example.com', 'password' => 'secret-password'])->assignRole($role);
        $count = Role::query()->count();

        $this->actingAs($this->admin)
            ->put($this->tenantUrl('/ar/admin/roles/'.$role->id), ['name' => 'Seller', 'permissions' => [Permission::findByName('User View List', 'web')->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($count, Role::query()->count());
        $this->assertSame('Seller', $role->fresh()->name);

        $this->actingAs($cashier->fresh())->get($this->tenantUrl('/ar/admin/currencies'))->assertForbidden();
    }

    public function test_system_roles_cannot_be_renamed_or_deleted(): void
    {
        $user = Role::findByName('User', 'web');
        $superAdmin = Role::findByName('Super Admin', 'web');

        $this->actingAs($this->admin)
            ->put($this->tenantUrl('/ar/admin/roles/'.$user->id), ['name' => 'Renamed', 'permissions' => []])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->put($this->tenantUrl('/ar/admin/roles/'.$superAdmin->id), ['name' => 'Super Admin', 'permissions' => []])
            ->assertSessionHasErrors('permissions');

        $this->assertTrue($superAdmin->fresh()->hasPermissionTo('User View List'));

        $this->actingAs($this->admin)
            ->delete($this->tenantUrl('/ar/admin/roles/'.$superAdmin->id))
            ->assertSessionHas('error');

        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_a_system_role_keeps_its_name_but_its_permissions_can_change(): void
    {
        $user = Role::findByName('User', 'web');
        $permission = Permission::findByName('Currency View List', 'web');

        $this->actingAs($this->admin)
            ->put($this->tenantUrl('/ar/admin/roles/'.$user->id), ['name' => 'User', 'permissions' => [$permission->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasPermissionTo($permission));
    }

    public function test_a_role_in_use_is_not_deleted_and_an_unused_one_is(): void
    {
        $used = Role::create(['name' => 'Used', 'guard_name' => 'web']);
        User::forceCreate(['name' => 'C', 'email' => 'c@example.com', 'password' => 'secret-password'])->assignRole($used);
        $unused = Role::create(['name' => 'Unused', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->delete($this->tenantUrl('/ar/admin/roles/'.$used->id))->assertSessionHas('error');
        $this->assertNotNull($used->fresh());

        $this->actingAs($this->admin)->delete($this->tenantUrl('/ar/admin/roles/'.$unused->id))->assertSessionHas('success');
        $this->assertNull($unused->fresh());
    }

    public function test_a_non_numeric_id_is_refused_not_a_server_error(): void
    {
        $this->actingAs($this->admin)->put($this->tenantUrl('/ar/admin/roles/abc'), [])->assertClientError();
    }

    public function test_each_route_needs_its_permission(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v@example.com', 'password' => 'secret-password'])->givePermissionTo('Currency View List');
        $role = Role::create(['name' => 'Target', 'guard_name' => 'web']);

        $this->actingAs($viewer);

        $this->get($this->tenantUrl('/ar/admin/roles'))->assertForbidden();
        $this->post($this->tenantUrl('/ar/admin/roles'), ['name' => 'X'])->assertForbidden();
        $this->put($this->tenantUrl('/ar/admin/roles/'.$role->id), ['name' => 'X'])->assertForbidden();
        $this->delete($this->tenantUrl('/ar/admin/roles/'.$role->id))->assertForbidden();
    }
}
