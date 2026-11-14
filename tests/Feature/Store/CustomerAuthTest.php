<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Store\Notifications\CustomerResetPassword;

/**
 * Buyers sign up, sign in and reset their password on the storefront. Fortify's /login stays the
 * staff's door; a staff member may still sign in here.
 */
class CustomerAuthTest extends StorefrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('store-login:buyer@example.com|127.0.0.1');
    }

    public function test_register_creates_a_customer_and_signs_in(): void
    {
        $this->assertSame('Store/Storefront/Account/Register', $this->page('/ar/account/register'));

        $this->register()->assertRedirect(route('store.account'))->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new@example.com')->sole();
        $this->assertSame(['Customer'], $user->getRoleNames()->all());
        $this->assertSame('0599123456', $user->phone);
        $this->assertTrue(Hash::check('Secret-pass-123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_validation(): void
    {
        $this->customer('taken@example.com');

        $this->register(['email' => 'TAKEN@example.com'])->assertSessionHasErrors('email');
        $this->register(['password_confirmation' => 'other'])->assertSessionHasErrors('password');
        $this->register(['phone' => '12'])->assertSessionHasErrors('phone');
        $this->register(['name' => ''])->assertSessionHasErrors('name');
        $this->assertGuest();
    }

    public function test_login_and_logout(): void
    {
        $user = $this->customer('buyer@example.com');

        $this->assertSame('Store/Storefront/Account/Login', $this->page('/ar/account/login'));

        $this->login('BUYER@example.com', 'Secret-pass-123', ['remember' => true])->assertRedirect(route('store.account'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);

        $this->post($this->siteUrl('/ar/account/logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_wrong_password_is_refused(): void
    {
        $this->customer('buyer@example.com');

        $this->login('buyer@example.com', 'wrong')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        $this->customer('buyer@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->login('buyer@example.com', 'wrong');
        }

        $this->login('buyer@example.com', 'Secret-pass-123')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('محاولات', session('errors')->first('email'));
    }

    public function test_login_returns_to_the_intended_page(): void
    {
        $this->customer('buyer@example.com');

        $this->get($this->siteUrl('/ar/account/orders'))->assertRedirect(route('store.account.login'));
        $this->login('buyer@example.com', 'Secret-pass-123')->assertRedirect($this->siteUrl('/ar/account/orders'));
    }

    public function test_a_local_back_address_is_kept_and_another_site_is_ignored(): void
    {
        $this->customer('buyer@example.com');

        $this->get($this->siteUrl('/ar/account/login?back=/ar/product/shirt'))->assertOk();
        $this->login('buyer@example.com', 'Secret-pass-123')->assertRedirect($this->siteUrl('/ar/product/shirt'));

        Auth::logout();
        $this->get($this->siteUrl('/ar/account/login?back=//evil.example/x'))->assertOk();
        $this->login('buyer@example.com', 'Secret-pass-123')->assertRedirect(route('store.account'));
    }

    public function test_a_two_factor_user_is_not_signed_in_on_the_storefront(): void
    {
        $staff = User::forceCreate(['name' => 'Staff', 'email' => 'staff2fa@example.com', 'password' => 'Secret-pass-123'])->assignRole(UserRole::SUPER_ADMIN->value);
        $staff->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();
        $this->assertTrue($staff->fresh()->hasEnabledTwoFactorAuthentication());

        $response = $this->login('staff2fa@example.com', 'Secret-pass-123');

        $response->assertSessionHasErrors('email');
        $this->assertSame('حسابات الموظفين المحمية بالتحقق بخطوتين تدخل من صفحة دخول الموظفين', session('errors')->first('email'));
        $this->assertGuest();
        $this->get($this->siteUrl('/ar/account'))->assertRedirect(route('store.account.login'));
        $this->assertSame(route('login'), $this->props('/ar/account/login', 'Store/Storefront/Account/Login')['staffLoginUrl']);
    }

    public function test_staff_without_two_factor_can_sign_in_on_the_storefront(): void
    {
        $staff = User::forceCreate(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'Secret-pass-123'])->assignRole(UserRole::SUPER_ADMIN->value);

        $this->login('staff@example.com', 'Secret-pass-123')->assertRedirect(route('store.account'));
        $this->assertAuthenticatedAs($staff);
        $this->get($this->siteUrl('/ar/account'))->assertOk();
    }

    public function test_register_is_limited_per_address(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->register(['email' => "n{$i}@example.com", 'name' => ''])->assertSessionHasErrors('name');
            auth()->logout();
        }

        $this->register(['email' => 'n11@example.com'])->assertSessionHasErrors('email');
        $this->assertSame(0, User::query()->where('email', 'n11@example.com')->count());
    }

    public function test_forgot_password_is_limited_per_address(): void
    {
        Notification::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->from($this->siteUrl('/ar/account/forgot-password'))->post($this->siteUrl('/ar/account/forgot-password'), ['email' => "f{$i}@example.com"])->assertSessionHasNoErrors();
        }

        $this->from($this->siteUrl('/ar/account/forgot-password'))->post($this->siteUrl('/ar/account/forgot-password'), ['email' => 'f6@example.com'])->assertSessionHasErrors('email');
    }

    public function test_the_reset_mail_goes_after_the_response(): void
    {
        Notification::fake();
        $this->customer('buyer@example.com');
        $sentDuringRequest = null;

        // Fired once the response is built, before deferred work runs.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, function () use (&$sentDuringRequest) {
            $sentDuringRequest = Notification::sentNotifications() !== [];
        });

        $this->post($this->siteUrl('/ar/account/forgot-password'), ['email' => 'buyer@example.com']);

        $this->assertFalse($sentDuringRequest, 'the mail was sent before the response was ready');
        Notification::assertSentTo(User::query()->where('email', 'buyer@example.com')->sole(), CustomerResetPassword::class);
    }

    public function test_a_missing_account_still_checks_a_password_hash(): void
    {
        Hash::spy();

        $this->login('nobody@example.com', 'whatever')->assertSessionHasErrors('email');

        Hash::shouldHaveReceived('check')->once();
    }

    public function test_registering_an_email_taken_in_another_case_is_a_form_error(): void
    {
        $this->customer('race@example.com');
        User::query()->where('email', 'race@example.com')->update(['email' => 'Race@Example.com']);

        // Two sign-ups at once: the form check passed for both, the database refuses the second.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\Modules\Store\Services\CustomerAuthService::class)->register(
            ['name' => 'Racer', 'phone' => '0599123456', 'email' => 'race@example.com', 'password' => 'Secret-pass-123'],
            request(),
        );
    }

    public function test_emails_are_unique_in_any_letter_case(): void
    {
        $this->customer('same@example.com');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        User::forceCreate(['name' => 'Twin', 'email' => 'SAME@example.com', 'password' => 'x-secret-123']);
    }

    public function test_staff_login_page_is_unchanged(): void
    {
        $this->get($this->siteUrl('/login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_forgot_password_sends_a_storefront_link_and_never_says_who_exists(): void
    {
        Notification::fake();
        $user = $this->customer('buyer@example.com');

        $this->assertSame('Store/Storefront/Account/ForgotPassword', $this->page('/ar/account/forgot-password'));

        $known = $this->post($this->siteUrl('/ar/account/forgot-password'), ['email' => 'buyer@example.com']);
        $unknown = $this->post($this->siteUrl('/ar/account/forgot-password'), ['email' => 'nobody@example.com']);

        $known->assertSessionHas('status');
        $this->assertSame(session('status'), $unknown->getSession()->get('status'));

        Notification::assertSentTo($user, CustomerResetPassword::class, function (CustomerResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertStringContainsString('/ar/account/reset-password/'.$notification->token, $mail->actionUrl);

            return true;
        });
        Notification::assertCount(1);
    }

    public function test_reset_password(): void
    {
        Notification::fake();
        $user = $this->customer('buyer@example.com');

        $this->post($this->siteUrl('/ar/account/forgot-password'), ['email' => 'buyer@example.com']);

        Notification::assertSentTo($user, CustomerResetPassword::class, function (CustomerResetPassword $notification) use ($user) {
            $this->get($this->siteUrl('/ar/account/reset-password/'.$notification->token.'?email=buyer@example.com'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Store/Storefront/Account/ResetPassword')->where('token', $notification->token)->where('email', 'buyer@example.com')->etc());

            $this->post($this->siteUrl('/ar/account/reset-password'), [
                'token' => 'wrong',
                'email' => 'buyer@example.com',
                'password' => 'New-secret-456',
                'password_confirmation' => 'New-secret-456',
            ])->assertSessionHasErrors('email');

            $this->post($this->siteUrl('/ar/account/reset-password'), [
                'token' => $notification->token,
                'email' => 'buyer@example.com',
                'password' => 'New-secret-456',
                'password_confirmation' => 'New-secret-456',
            ])->assertRedirect(route('store.account.login'))->assertSessionHasNoErrors();

            $this->assertTrue(Hash::check('New-secret-456', $user->fresh()->password));

            return true;
        });
    }

    public function test_guests_of_account_pages_go_to_the_storefront_login(): void
    {
        $this->get($this->siteUrl('/ar/account'))->assertRedirect(route('store.account.login'));
        // The admin still sends guests to the staff sign-in.
        $this->get($this->siteUrl('/ar/admin'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_skip_the_guest_pages(): void
    {
        $this->actingAs($this->customer('buyer@example.com'));

        $this->get($this->siteUrl('/ar/account/login'))->assertRedirect(route('store.account'));
        $this->get($this->siteUrl('/ar/account/register'))->assertRedirect(route('store.account'));
    }

    public function test_the_layout_knows_the_account(): void
    {
        $guest = $this->props('/ar', 'Store/Storefront/Home')['store']['account'];
        $this->assertFalse($guest['signed_in']);
        $this->assertSame(route('store.account.login'), $guest['login_url']);

        $this->actingAs($this->customer('buyer@example.com'));
        $signedIn = $this->props('/ar', 'Store/Storefront/Home')['store']['account'];

        $this->assertTrue($signedIn['signed_in']);
        $this->assertSame('Buyer', $signedIn['name']);
        $this->assertSame(route('store.account.orders'), $signedIn['orders_url']);
    }

    private function customer(string $email): User
    {
        return User::forceCreate(['name' => 'Buyer', 'email' => $email, 'phone' => '0599000111', 'password' => 'Secret-pass-123'])
            ->assignRole(UserRole::CUSTOMER->value);
    }

    private function page(string $path): string
    {
        $component = '';
        $this->get($this->siteUrl($path))->assertOk()->assertInertia(function (Assert $page) use (&$component) {
            $component = $page->toArray()['component'];
            $page->etc();
        });

        return $component;
    }

    private function register(array $overrides = []): TestResponse
    {
        return $this->from($this->siteUrl('/ar/account/register'))->post($this->siteUrl('/ar/account/register'), [
            'name' => 'New Buyer',
            'phone' => '+970 599 123 456',
            'email' => 'new@example.com',
            'password' => 'Secret-pass-123',
            'password_confirmation' => 'Secret-pass-123',
            ...$overrides,
        ]);
    }

    private function login(string $email, string $password, array $extra = []): TestResponse
    {
        return $this->from($this->siteUrl('/ar/account/login'))->post($this->siteUrl('/ar/account/login'), ['email' => $email, 'password' => $password, ...$extra]);
    }
}
