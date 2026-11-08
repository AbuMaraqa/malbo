<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;

class UserPagesTest extends DatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->admin = $this->makeUser('admin@example.com')->assignRole('Super Admin');
    }

    public function test_the_list_shows_users_with_their_roles(): void
    {
        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin/users?filter[email]=admin@example.com'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Users/Index')
                ->where('table.meta.total', 1)
                ->where('table.data.0.email', 'admin@example.com')
                ->where('table.data.0.roles.0.name', 'Super Admin')
                ->missing('table.data.0.password')
                ->has('roleOptions')
                ->has('branchOptions'));
    }

    public function test_search_finds_a_user_in_another_letter_case(): void
    {
        $this->makeUser('findme@example.com', 'Findable Person');

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin/users?filter[search]=FINDABLE'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.data.0.email', 'findme@example.com'));
    }

    public function test_the_filters_narrow_the_list(): void
    {
        $role = Role::findByName('User', 'web');
        $this->makeUser('role@example.com', 'With Role', '0599')->assignRole($role);

        foreach ([
            'role' => (string) $role->id,
            'name' => 'with ROLE',
            'email' => 'ROLE@example',
            'phone' => '0599',
        ] as $filter => $value) {
            $this->actingAs($this->admin)
                ->get($this->siteUrl('/ar/admin/users?filter['.$filter.']='.urlencode($value)))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('table.meta.total', 1)
                    ->where('table.data.0.email', 'role@example.com'));
        }
    }

    public function test_the_date_and_branch_filters_do_not_fail(): void
    {
        foreach (['filter[created_at_from]=2020-01-01', 'filter[created_at_to]=not-a-date', 'filter[branch]=999999', 'filter[role]=abc'] as $query) {
            $this->actingAs($this->admin)
                ->get($this->siteUrl('/ar/admin/users?'.$query))
                ->assertOk();
        }
    }

    public function test_adding_a_user_saves_roles_and_hashes_the_password(): void
    {
        $this->actingAs($this->admin)
            ->post($this->siteUrl('/ar/admin/users'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertTrue($user->hasRole('User'));
    }

    public function test_a_mixed_case_email_is_saved_in_lower_case_and_can_log_in(): void
    {
        $this->actingAs($this->admin)
            ->post($this->siteUrl('/ar/admin/users'), $this->payload(['email' => 'Mixed.Case@Example.com']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::query()->where('email', 'mixed.case@example.com')->exists());

        auth()->guard('web')->logout();

        $this->post($this->siteUrl('/login'), ['email' => 'Mixed.Case@Example.com', 'password' => 'secret-password']);

        $this->assertAuthenticated();
    }

    public function test_validation_errors_are_returned(): void
    {
        $this->actingAs($this->admin)
            ->post($this->siteUrl('/ar/admin/users'), $this->payload(['email' => 'ADMIN@example.com', 'password' => 'short', 'roles' => []]))
            ->assertSessionHasErrors(['email', 'password', 'roles']);
    }

    public function test_a_bad_role_or_branch_id_is_a_validation_error_not_a_server_error(): void
    {
        $this->actingAs($this->admin)
            ->post($this->siteUrl('/ar/admin/users'), $this->payload(['roles' => ['abc'], 'branch_id' => 'abc']))
            ->assertSessionHasErrors(['roles.0', 'branch_id']);
    }

    public function test_the_edit_page_sends_the_form_values_without_secrets(): void
    {
        $user = $this->makeUser('edit@example.com')->assignRole('User');

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin/users/'.$user->id.'/edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Users/Edit')
                ->where('user.email', 'edit@example.com')
                ->where('user.roles.0', Role::findByName('User', 'web')->id)
                ->missing('user.password'));
    }

    public function test_editing_updates_the_same_user_and_a_blank_password_keeps_the_old_one(): void
    {
        $user = $this->makeUser('edit@example.com')->assignRole('User');
        $count = User::query()->count();

        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/users/'.$user->id), $this->payload(['name' => 'Edited', 'email' => 'edit@example.com', 'password' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($count, User::query()->count());
        $this->assertSame('Edited', $user->fresh()->name);
        $this->assertTrue(Hash::check('secret-password', $user->fresh()->password));
    }

    public function test_deleting_removes_the_user(): void
    {
        $user = $this->makeUser('gone@example.com');

        $this->actingAs($this->admin)
            ->delete($this->siteUrl('/ar/admin/users/'.$user->id))
            ->assertSessionHas('success');

        $this->assertNull($user->fresh());
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->delete($this->siteUrl('/ar/admin/users/'.$this->admin->id))
            ->assertSessionHas('error');

        $this->assertNotNull($this->admin->fresh());
    }

    public function test_the_last_super_admin_keeps_the_role(): void
    {
        foreach (User::role('Super Admin')->whereKeyNot($this->admin->id)->get() as $other) {
            $other->removeRole('Super Admin');
        }

        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/users/'.$this->admin->id), $this->payload(['email' => 'admin@example.com', 'password' => '']))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($this->admin->fresh()->hasRole('Super Admin'));
    }

    public function test_a_user_who_created_records_that_would_cascade_is_not_deleted(): void
    {
        $user = $this->makeUser('creator@example.com');
        $this->makeOrder($user);

        $this->actingAs($this->admin)
            ->delete($this->siteUrl('/ar/admin/users/'.$user->id))
            ->assertSessionHas('error');

        $this->assertNotNull($user->fresh());
    }

    public function test_only_a_super_admin_gives_or_touches_the_super_admin_role(): void
    {
        $manager = $this->makeUser('manager@example.com')->givePermissionTo(['User View List', 'User Create', 'User Update', 'User Delete']);
        $superAdmin = Role::findByName('Super Admin', 'web');

        $this->actingAs($manager)
            ->get($this->siteUrl('/ar/admin/users/add'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleOptions', fn ($options) => collect($options)->doesntContain('value', $superAdmin->id)));

        $this->actingAs($manager)
            ->post($this->siteUrl('/ar/admin/users'), $this->payload(['roles' => [$superAdmin->id]]))
            ->assertSessionHasErrors('roles');

        $this->actingAs($manager)->get($this->siteUrl('/ar/admin/users/'.$this->admin->id.'/edit'))->assertForbidden();
        $this->actingAs($manager)->delete($this->siteUrl('/ar/admin/users/'.$this->admin->id))->assertForbidden();
    }

    public function test_a_non_numeric_id_is_a_not_found_not_a_server_error(): void
    {
        $this->actingAs($this->admin)->get($this->siteUrl('/ar/admin/users/abc/edit'))->assertNotFound();
    }

    public function test_each_route_needs_its_permission(): void
    {
        $viewer = $this->makeUser('viewer@example.com')->givePermissionTo('Currency View List');
        $target = $this->makeUser('target@example.com');

        $this->actingAs($viewer);

        $this->get($this->siteUrl('/ar/admin/users'))->assertForbidden();
        $this->get($this->siteUrl('/ar/admin/users/add'))->assertForbidden();
        $this->post($this->siteUrl('/ar/admin/users'), $this->payload())->assertForbidden();
        $this->get($this->siteUrl('/ar/admin/users/'.$target->id.'/edit'))->assertForbidden();
        $this->put($this->siteUrl('/ar/admin/users/'.$target->id), $this->payload())->assertForbidden();
        $this->delete($this->siteUrl('/ar/admin/users/'.$target->id))->assertForbidden();
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->siteUrl('/ar/admin/users'))->assertRedirect();
    }

    private function makeUser(string $email, string $name = 'Someone', ?string $phone = null): User
    {
        return User::forceCreate(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => 'secret-password']);
    }

    private function makeOrder(User $creator): void
    {
        \Modules\Items\Entities\Order::query()->forceCreate([
            'order_number' => 'T-'.$creator->id,
            'sub_total' => 10,
            'total' => 10,
            'delivery_address' => 'Address',
            'contact_phone' => '0599',
            'created_by' => $creator->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'New User',
            'email' => 'new@example.com',
            'phone' => '0599000000',
            'password' => 'secret-password',
            'roles' => [Role::findByName('User', 'web')->id],
            'branch_id' => null,
            ...$overrides,
        ];
    }
}
