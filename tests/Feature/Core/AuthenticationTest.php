<?php

namespace Tests\Feature\Core;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Tests\DatabaseTestCase;

class AuthenticationTest extends DatabaseTestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get($this->siteUrl('/login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->makeUser();

        $response = $this->post($this->siteUrl('/login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', ['locale' => app()->getLocale()]));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->makeUser();

        $this->post($this->siteUrl('/login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    private function makeUser(): User
    {
        return User::forceCreate(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password']);
    }
}
