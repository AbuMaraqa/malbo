<?php

namespace Tests\Feature\Platform;

use App\Http\Controllers\Platform\LoginController;
use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Tests\CentralTestCase;

class PlatformAuthTest extends CentralTestCase
{
    private const LOGIN = 'http://localhost/ar/platform/login';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(LoginController::limiterKey('a@p.test', '127.0.0.1'));
        RateLimiter::clear(LoginController::limiterKey('a@p.test'));
    }

    public function test_command_creates_admin_with_hashed_password(): void
    {
        $this->artisan('platform:admin', ['email' => 'a@p.test', '--name' => 'A', '--password' => 'secret-pass-12'])->assertExitCode(0);

        $admin = PlatformAdmin::firstWhere('email', 'a@p.test');
        $this->assertSame('A', $admin->name);
        $this->assertTrue(Hash::check('secret-pass-12', $admin->password));
    }

    public function test_command_saves_the_email_in_lower_case(): void
    {
        $this->artisan('platform:admin', ['email' => 'Mixed@P.Test', '--name' => 'A', '--password' => 'secret-pass-12'])->assertExitCode(0);

        $this->assertSame('mixed@p.test', PlatformAdmin::first()->email);
    }

    public function test_command_asks_for_the_password(): void
    {
        $this->artisan('platform:admin', ['email' => 'a@p.test', '--name' => 'A'])
            ->expectsQuestion('Password', 'secret-pass-12')
            ->expectsQuestion('Confirm the password', 'secret-pass-12')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('secret-pass-12', PlatformAdmin::firstWhere('email', 'a@p.test')->password));
    }

    public function test_command_updates_existing_admin(): void
    {
        $this->artisan('platform:admin', ['email' => 'a@p.test', '--name' => 'A', '--password' => 'first-pass-1234'])->assertExitCode(0);
        $this->artisan('platform:admin', ['email' => 'a@p.test', '--password' => 'second-pass-123'])->assertExitCode(0);

        $this->assertSame(1, PlatformAdmin::count());
        $this->assertSame('A', PlatformAdmin::first()->name);
        $this->assertTrue(Hash::check('second-pass-123', PlatformAdmin::first()->password));
    }

    public function test_admin_signs_in(): void
    {
        $admin = $this->admin();

        $this->post(self::LOGIN, ['email' => 'a@p.test', 'password' => 'secret-pass-12'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('http://localhost/ar/platform');

        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_email_in_another_letter_case_signs_in(): void
    {
        $admin = $this->admin();

        $this->post(self::LOGIN, ['email' => 'A@P.Test', 'password' => 'secret-pass-12'])->assertRedirect('http://localhost/ar/platform');

        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->admin();

        $this->post(self::LOGIN, ['email' => 'a@p.test', 'password' => 'wrong-pass'])->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_sixth_attempt_in_a_minute_is_throttled(): void
    {
        $this->admin();

        foreach (range(1, LoginController::MAX_ATTEMPTS) as $ignored) {
            $this->post(self::LOGIN, ['email' => 'a@p.test', 'password' => 'wrong-pass']);
        }

        // The right password, in another letter case: still the same counter.
        $this->post(self::LOGIN, ['email' => 'A@P.test', 'password' => 'secret-pass-12'])
            ->assertSessionHasErrors(['email' => __('Too many sign-in attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn(LoginController::limiterKey('a@p.test', '127.0.0.1')),
            ])]);

        $this->assertGuest('platform');
    }

    public function test_many_failures_on_one_email_lock_it_from_every_address(): void
    {
        $this->admin();

        foreach (range(1, LoginController::MAX_ATTEMPTS_PER_EMAIL) as $ignored) {
            RateLimiter::hit(LoginController::limiterKey('a@p.test'), 3600); // failures from other addresses
        }

        $this->post(self::LOGIN, ['email' => 'a@p.test', 'password' => 'secret-pass-12'])->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_failed_attempt_does_not_keep_the_password(): void
    {
        $this->admin();

        $this->from(self::LOGIN)->post(self::LOGIN, ['email' => 'a@p.test', 'password' => 'wrong-pass'])
            ->assertSessionHasInput('email', 'a@p.test')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_command_requires_a_long_password(): void
    {
        $this->artisan('platform:admin', ['email' => 'a@p.test', '--name' => 'A', '--password' => 'short-pass'])->assertExitCode(1);

        $this->assertSame(0, PlatformAdmin::count());
    }

    public function test_changing_the_password_signs_out_remembered_devices(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['remember_token' => 'old-token'])->save();

        $this->artisan('platform:admin', ['email' => 'a@p.test', '--password' => 'another-secret-pass'])->assertExitCode(0);

        $this->assertNotSame('old-token', $admin->fresh()->remember_token);
    }

    public function test_guest_on_an_alias_host_stays_on_that_host(): void
    {
        $this->get('http://127.0.0.1/ar/platform')->assertRedirect('http://127.0.0.1/ar/platform/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get(self::LOGIN)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Platform/Login')
            ->where('loginUrl', self::LOGIN));
    }

    public function test_signed_in_admin_skips_the_login_page(): void
    {
        $this->actingAs($this->admin(), 'platform');

        $this->get(self::LOGIN)->assertRedirect('http://localhost/ar/platform');
    }

    public function test_guest_is_sent_to_platform_login(): void
    {
        $this->get('http://localhost/ar/platform')->assertRedirect(self::LOGIN);
    }

    public function test_signed_in_admin_reaches_dashboard_and_signs_out(): void
    {
        $this->actingAs($this->admin(), 'platform');

        $this->get('http://localhost/ar/platform')->assertOk();
        $this->post('http://localhost/ar/platform/logout')->assertRedirect(self::LOGIN);

        $this->assertGuest('platform');
    }

    public function test_platform_login_is_404_on_store_host(): void
    {
        $this->createTenant('xstore');

        $this->get('http://xstore.localhost/ar/platform/login')->assertNotFound();
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'A', 'email' => 'a@p.test', 'password' => 'secret-pass-12']);
    }
}
