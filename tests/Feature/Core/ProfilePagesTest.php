<?php

namespace Tests\Feature\Core;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Tests\TenantTestCase;

/**
 * Behaviour of core's ProfileInformationTest and UpdatePasswordTest on the Inertia profile page.
 */
class ProfilePagesTest extends TenantTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->user = User::forceCreate(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password'])->assignRole('User');
        $this->user->givePermissionTo('Currency View List');
    }

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($this->user)
            ->get($this->tenantUrl('/ar/admin/profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Profile/Index')
                ->where('profile.name', 'Staff')
                ->where('profile.email', 'staff@example.com')
                ->where('twoFactor.enabled', false)
                ->where('twoFactor.qrCodeSvg', null)
                ->missing('profile.password'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile'), ['name' => 'Test Name', 'email' => 'Test@Example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Test Name', $this->user->fresh()->name);
        $this->assertSame('test@example.com', $this->user->fresh()->email);
    }

    public function test_the_email_must_stay_unique(): void
    {
        User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']);

        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile'), ['name' => 'Staff', 'email' => 'OTHER@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_avatar_and_cover_are_uploaded_and_removed(): void
    {
        Storage::fake('media');

        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile'), [
                'name' => 'Staff',
                'email' => 'staff@example.com',
                'avatar' => UploadedFile::fake()->image('a.png'),
                'cover_photo' => UploadedFile::fake()->image('c.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get($this->tenantUrl('/ar/admin/profile'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('profile.avatar', 1)
                ->has('profile.cover_photo', 1)
                ->whereNot('profile.cover_url', null));

        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile'), ['name' => 'Staff', 'email' => 'staff@example.com', 'remove_avatar' => true])
            ->assertSessionHasNoErrors();

        $fresh = $this->user->fresh();
        $this->assertSame('', $fresh->getFirstMediaUrl('avatar'));
        $this->assertNotSame('', $fresh->getFirstMediaUrl('cover_photo'));
    }

    public function test_an_avatar_must_be_an_image(): void
    {
        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile'), [
                'name' => 'Staff',
                'email' => 'staff@example.com',
                'avatar' => UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_password_can_be_updated(): void
    {
        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile/password'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));
    }

    public function test_current_password_must_be_correct(): void
    {
        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile/password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_new_passwords_must_match(): void
    {
        $this->actingAs($this->user)
            ->put($this->tenantUrl('/ar/admin/profile/password'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'wrong-password',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_a_customer_only_user_does_not_reach_the_admin_profile(): void
    {
        $customer = User::forceCreate(['name' => 'C', 'email' => 'c@example.com', 'password' => 'password'])->assignRole('Customer');

        $this->actingAs($customer)->get($this->tenantUrl('/ar/admin/profile'))->assertRedirect();
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('/ar/admin/profile'))->assertRedirect();
    }

    public function test_fortify_endpoints_update_the_store_user(): void
    {
        $this->actingAs($this->user)
            ->put($this->tenantUrl('/user/profile-information'), ['name' => 'Via Fortify', 'email' => 'Via@Example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('via@example.com', $this->user->fresh()->email);

        $this->actingAs($this->user->fresh())
            ->put($this->tenantUrl('/user/password'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));
    }
}
