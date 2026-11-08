<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Platform\Provisioning\NewStoreData;
use App\Platform\Provisioning\StoreProvisioner;
use App\Platform\Provisioning\StoreProvisioningFailed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Core\Entities\User;
use Modules\Store\Settings\StoreAppearanceSettings;
use Tests\CentralTestCase;

class StoreSignUpTest extends CentralTestCase
{
    private const SIGN_UP = 'http://localhost/ar/register-store';

    /** The key ThrottleRequests uses for the named limiter and this address. */
    private const LIMITER_KEY = 'platform-signup127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(md5(self::LIMITER_KEY));
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            RateLimiter::clear(md5(self::LIMITER_KEY));
        }

        parent::tearDown();
    }

    public function test_sign_up_page_is_on_the_platform_host(): void
    {
        $this->get(self::SIGN_UP)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Platform/SignUp')
            ->where('baseDomain', 'localhost')
            ->where('storeUrl', self::SIGN_UP)
            ->where('slugUrl', self::SIGN_UP.'/slug')
            ->has('businessTypes', 7)
            ->has('fonts', count(config('store.fonts')))
            ->has('colors')
            ->where('defaults.font', config('store.default_font')));
    }

    public function test_landing_page_links_to_sign_up(): void
    {
        $this->get('http://localhost/ar')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Platform/Landing')
            ->where('signUpUrl', self::SIGN_UP));
    }

    public function test_platform_pages_have_arabic_texts_without_the_admin_texts(): void
    {
        $this->get('http://localhost/ar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('translations.Create your store', 'أنشئ متجرك')
            ->missing('translations.Currency View List'));
    }

    public function test_a_visitor_creates_a_store_and_lands_in_its_admin(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant('su-shop'));

        $response = $this->post(self::SIGN_UP, $this->form(['slug' => 'SU-Shop', 'ownerEmail' => 'Owner@SU-Shop.ps', 'businessType' => 'fashion', 'ownerPhone' => '0599000000']));

        $response->assertSessionHasNoErrors()->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('su-shop.localhost', $location);
        $this->assertStringContainsString('/impersonate/', $location);

        $tenant = Tenant::find('su-shop');
        $this->assertNotNull($tenant);
        $this->assertSame('owner@su-shop.ps', $tenant->owner_email);
        $this->assertSame('fashion', $tenant->business_type);
        $this->assertNotNull($tenant->owner_user_id);

        // Saved in lower case: login lowers its input and PostgreSQL compares exactly.
        $tenant->run(fn () => $this->assertSame('owner@su-shop.ps', User::find($tenant->owner_user_id)->email));

        // Following the link signs the visitor in on the new store's admin.
        $this->get($location)->assertRedirect('/ar/admin');
        $this->assertTrue(auth('web')->check());
        tenancy()->end();
    }

    public function test_the_branding_chosen_in_the_wizard_is_applied_to_the_new_store(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant('su-brand'));

        $this->post(self::SIGN_UP, $this->form([
            'slug' => 'su-brand',
            'color' => '#0f766e',
            'font' => 'cairo',
            'logo' => UploadedFile::fake()->image('logo.png', 80, 80),
        ]))->assertSessionHasNoErrors();

        Tenant::find('su-brand')->run(function () {
            $settings = app(StoreAppearanceSettings::class);

            $this->assertSame('#0F766E', $settings->primary_color);
            $this->assertSame('cairo', $settings->font);
            $this->assertNotNull($settings->logo_path);
            $this->assertTrue(Storage::disk('public')->exists($settings->logo_path));
        });
    }

    public function test_wrong_branding_is_refused(): void
    {
        $this->post(self::SIGN_UP, $this->form([
            'slug' => 'su-bad-look',
            'color' => 'red',
            'font' => 'comic-sans',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors(['color', 'font', 'logo']);

        $this->assertNull(Tenant::find('su-bad-look'));
    }

    public function test_the_address_is_checked_while_typing(): void
    {
        $this->getJson(self::SIGN_UP.'/slug?slug=admin')->assertOk()->assertJson(['available' => false, 'message' => __('This store address is reserved.')]);
        $this->getJson(self::SIGN_UP.'/slug?slug=a')->assertOk()->assertJson(['available' => false]);
        $this->getJson(self::SIGN_UP.'/slug')->assertOk()->assertJson(['available' => false]);
        $this->getJson(self::SIGN_UP.'/slug?slug=Free-Name-Here')->assertOk()->assertJson(['slug' => 'free-name-here', 'available' => true, 'message' => null]);

        $this->taken();
        $this->getJson(self::SIGN_UP.'/slug?slug=taken-shop')->assertOk()->assertJson(['available' => false, 'message' => __('This store address is already taken.')]);
    }

    public function test_a_taken_address_is_refused_on_submit(): void
    {
        $this->taken();

        $this->post(self::SIGN_UP, $this->form(['slug' => 'taken-shop']))
            ->assertSessionHasErrors(['slug' => __('This store address is already taken.')]);
    }

    public function test_required_fields_password_and_terms(): void
    {
        $this->post(self::SIGN_UP, [
            'storeName' => 'متجر',
            'slug' => 'su-bad',
            'ownerName' => 'Owner',
            'ownerEmail' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'other',
        ])->assertSessionHasErrors(['ownerEmail', 'password', 'terms']);

        $this->assertNull(Tenant::find('su-bad'));
    }

    public function test_a_filled_trap_field_creates_nothing(): void
    {
        $this->post(self::SIGN_UP, $this->form(['slug' => 'su-bot', 'website' => 'http://spam.example']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Tenant::find('su-bot'));
    }

    public function test_sign_ups_are_limited_per_address(): void
    {
        foreach (range(1, 3) as $ignored) {
            RateLimiter::hit(md5(self::LIMITER_KEY), 3600);
        }

        $this->post(self::SIGN_UP, $this->form(['slug' => 'su-many']))->assertSessionHasErrors('provisioning');

        $this->assertNull(Tenant::find('su-many'));
    }

    public function test_a_rejected_form_does_not_use_up_the_limit(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post(self::SIGN_UP, $this->form(['slug' => 'su-typo', 'ownerEmail' => 'nope']))->assertSessionHasErrors('ownerEmail');
        }

        $this->assertSame(0, (int) RateLimiter::attempts(md5(self::LIMITER_KEY)));
    }

    public function test_a_created_store_uses_up_the_limit(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant('su-count'));

        $this->post(self::SIGN_UP, $this->form(['slug' => 'su-count']))->assertSessionHasNoErrors();

        $this->assertSame(1, (int) RateLimiter::attempts(md5(self::LIMITER_KEY)));
    }

    public function test_a_failed_provisioning_shows_a_clear_message_and_counts_nothing(): void
    {
        $this->app->instance(StoreProvisioner::class, new class extends StoreProvisioner
        {
            public function provision(NewStoreData $data): Tenant
            {
                throw new StoreProvisioningFailed(__('The store could not be created. Nothing was kept; please try again.'));
            }
        });

        $this->from(self::SIGN_UP)->post(self::SIGN_UP, $this->form(['slug' => 'su-fails']))
            ->assertRedirect(self::SIGN_UP)
            ->assertSessionHasErrors(['provisioning' => __('The store could not be created. Nothing was kept; please try again.')]);

        $this->assertSame(0, (int) RateLimiter::attempts(md5(self::LIMITER_KEY)));
    }

    private function taken(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'taken-shop']));
        $tenant->domains()->create(['domain' => 'taken-shop.localhost']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'storeName' => 'متجر التجربة',
            'slug' => 'su-shop',
            'ownerName' => 'Owner Name',
            'ownerEmail' => 'owner@su-shop.ps',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'terms' => true,
            ...$overrides,
        ];
    }
}
