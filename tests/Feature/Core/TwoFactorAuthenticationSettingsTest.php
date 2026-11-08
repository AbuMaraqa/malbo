<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use PragmaRX\Google2FA\Google2FA;
use Tests\DatabaseTestCase;

/**
 * Behaviour of core's TwoFactorAuthenticationSettingsTest on the profile page.
 */
class TwoFactorAuthenticationSettingsTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->user = User::forceCreate(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password'])->assignRole('User');
    }

    public function test_two_factor_authentication_can_be_enabled_and_confirmed(): void
    {
        $this->enable();

        $user = $this->user->fresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());

        $this->actingAs($this->user)
            ->get($this->siteUrl('/ar/admin/profile'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('twoFactor.enabled', false)
                ->whereNot('twoFactor.qrCodeSvg', null)
                ->whereNot('twoFactor.secretKey', null)
                ->where('twoFactor.recoveryCodes', null));

        $this->confirm()->assertSessionHasNoErrors();

        $this->assertTrue($this->user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_recovery_codes_are_shown_once_after_confirming(): void
    {
        $this->enable();

        $this->followingRedirects();
        $this->confirm()->assertInertia(fn (Assert $page) => $page->has('twoFactor.recoveryCodes', 8)->where('twoFactor.qrCodeSvg', null));

        $this->actingAs($this->user)
            ->get($this->siteUrl('/ar/admin/profile'))
            ->assertInertia(fn (Assert $page) => $page->where('twoFactor.enabled', true)->where('twoFactor.recoveryCodes', null));
    }

    public function test_the_setup_secret_needs_a_recently_confirmed_password(): void
    {
        $this->enable();
        session()->forget('auth.password_confirmed_at');

        $this->actingAs($this->user)
            ->get($this->siteUrl('/ar/admin/profile'))
            ->assertInertia(fn (Assert $page) => $page->where('twoFactor.qrCodeSvg', null)->where('twoFactor.secretKey', null));
    }

    public function test_enabling_needs_the_right_password(): void
    {
        $this->actingAs($this->user)
            ->post($this->siteUrl('/ar/admin/profile/two-factor'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertNull($this->user->fresh()->two_factor_secret);
    }

    public function test_a_wrong_code_is_an_error_on_the_code_field(): void
    {
        $this->enable();

        $this->actingAs($this->user)
            ->post($this->siteUrl('/ar/admin/profile/two-factor/confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($this->user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $this->enable();
        $this->confirm();

        $before = $this->user->fresh()->recoveryCodes();

        $this->actingAs($this->user)
            ->post($this->siteUrl('/ar/admin/profile/two-factor/recovery-codes'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $after = $this->user->fresh()->recoveryCodes();

        $this->assertCount(8, $after);
        $this->assertCount(8, array_diff($before, $after));
    }

    public function test_two_factor_authentication_can_be_disabled(): void
    {
        $this->enable();
        $this->assertNotNull($this->user->fresh()->two_factor_secret);

        $this->actingAs($this->user)
            ->delete($this->siteUrl('/ar/admin/profile/two-factor'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->user->fresh()->two_factor_secret);
    }

    public function test_login_asks_for_the_code_once_two_factor_is_confirmed(): void
    {
        $this->enable();
        $this->confirm();

        auth()->guard('web')->logout();

        $this->post($this->siteUrl('/login'), ['email' => 'staff@example.com', 'password' => 'password'])
            ->assertRedirect($this->siteUrl('/two-factor-challenge'));

        $this->assertGuest();
    }

    private function enable(): void
    {
        $this->actingAs($this->user)
            ->post($this->siteUrl('/ar/admin/profile/two-factor'), ['password' => 'password'])
            ->assertSessionHasNoErrors();
    }

    private function confirm(): \Illuminate\Testing\TestResponse
    {
        $code = (new Google2FA)->getCurrentOtp(decrypt($this->user->fresh()->two_factor_secret));

        return $this->actingAs($this->user)
            ->post($this->siteUrl('/ar/admin/profile/two-factor/confirm'), ['code' => $code]);
    }
}
