<?php

namespace Tests\Feature\Core;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Tests\TenantTestCase;

class PasswordResetTest extends TenantTestCase
{
    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get($this->tenantUrl('/forgot-password'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $this->post($this->tenantUrl('/forgot-password'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $this->post($this->tenantUrl('/forgot-password'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) {
            $this->get($this->tenantUrl('/reset-password/').$notification->token)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')->where('token', $notification->token));

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $this->post($this->tenantUrl('/forgot-password'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
            $this->post($this->tenantUrl('/reset-password'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    private function makeUser(): User
    {
        return User::forceCreate(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password']);
    }
}
