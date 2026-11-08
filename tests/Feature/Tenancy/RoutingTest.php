<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Settings\GeneralSettings;
use Tests\TenantTestCase;

/**
 * Every request here starts like a real one: with tenancy ended, so the routes have to identify
 * the store (or the platform) from the host themselves. Requests initialize tenancy on their own,
 * which drops a tenant transaction, so this class runs without it and deletes the users and
 * restores the settings it changed.
 */
class RoutingTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    /** @var list<int> users created in `testing` */
    private array $userIds = [];

    /** @var list<int> users created in the second store */
    private array $otherUserIds = [];

    protected function tearDown(): void
    {
        if ($this->app && $this->otherUserIds) {
            $this->secondTenant()->run(fn () => User::whereKey($this->otherUserIds)->get()->each->delete());
        }

        if ($this->app && $this->userIds) {
            tenancy()->initialize($this->tenant);
            User::whereKey($this->userIds)->get()->each->delete(); // HasRoles detaches roles and permissions
        }

        parent::tearDown();
    }

    public function test_platform_host_serves_landing(): void
    {
        $this->asCentral();

        $this->get('http://localhost/ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Platform/Landing')->where('locale', 'ar'));

        $this->asCentral();

        $this->get('http://localhost/en')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Platform/Landing')->where('locale', 'en'));
    }

    public function test_landing_renders_with_the_platform_font_and_no_store_theme(): void
    {
        $this->asCentral();

        $this->get('http://localhost/ar')
            ->assertOk()
            ->assertSee('fonts.googleapis.com/css2?family=Tajawal', false)
            ->assertDontSee('id="app-theme"', false);
    }

    public function test_store_host_serves_store_home(): void
    {
        $other = $this->secondTenant();
        $original = $this->siteNames(['testing' => 'Home Of Testing', $other->getTenantKey() => 'Home Of Other']);

        try {
            // Settings resolved in another store earlier in the process must not be served.
            $other->run(fn () => $this->assertSame('Home Of Other', app(GeneralSettings::class)->site_name));
            tenancy()->initialize($other);
            app(GeneralSettings::class)->site_name;

            $this->asCentral();

            $this->get($this->tenantUrl('/ar'))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('Store/Storefront/Home'))
                ->assertSee('Home Of Testing')
                ->assertDontSee('Home Of Other');

            $this->assertSame('testing', tenant('id'), 'The request must initialize the store itself.');
        } finally {
            $this->siteNames($original);
        }
    }

    public function test_root_redirects_to_default_locale_on_both_hosts(): void
    {
        $default = Locales::default();

        $this->asCentral();
        $this->get('http://localhost/')->assertRedirect("http://localhost/{$default}");

        $this->asCentral();
        $this->get($this->tenantUrl('/'))->assertRedirect($this->tenantUrl("/{$default}"));

        // A locale chosen earlier (kept in the session) wins.
        $other = $default === 'ar' ? 'en' : 'ar';

        $this->asCentral();
        $this->withSession(['locale' => $other])->get($this->tenantUrl('/'))->assertRedirect($this->tenantUrl("/{$other}"));
    }

    public function test_admin_requires_login_on_store(): void
    {
        $this->asCentral();

        $this->get($this->tenantUrl('/ar/admin'))->assertRedirect($this->tenantUrl('/login'));
    }

    public function test_admin_is_404_on_platform_host(): void
    {
        $this->asCentral();

        $this->get('http://localhost/ar/admin')->assertNotFound();
    }

    public function test_unknown_store_host_is_404(): void
    {
        $this->asCentral();
        $this->get('http://nope.localhost/ar')->assertNotFound();

        $this->asCentral();
        $this->get('http://nope.localhost/')->assertNotFound();

        $this->asCentral();
        $this->getJson('http://nope.localhost/api/v1/items/categories')
            ->assertNotFound()
            ->assertJson(['status' => false, 'message' => 'Resource not found.']);
    }

    public function test_suspended_store_shows_unavailable(): void
    {
        $this->asCentral();
        Tenant::whereKey('testing')->update(['status' => Tenant::STATUS_SUSPENDED]);

        try {
            $this->get($this->tenantUrl('/ar'))->assertStatus(503);

            $this->asCentral();
            $this->get($this->tenantUrl('/ar/admin'))->assertStatus(503);

            $this->asCentral();
            $this->getJson($this->tenantUrl('/api/v1/items/categories'))
                ->assertStatus(503)
                ->assertJson(['status' => false]);
        } finally {
            Tenant::whereKey('testing')->update(['status' => Tenant::STATUS_ACTIVE]);
        }
    }

    public function test_staff_reaches_admin(): void
    {
        $user = $this->userWithRoles(UserRole::SUPER_ADMIN->value);

        $this->asCentral();

        $this->actingAs($user)->get($this->tenantUrl('/ar/admin'))->assertOk();
    }

    public function test_customer_is_sent_to_storefront(): void
    {
        $user = $this->userWithRoles(UserRole::CUSTOMER->value);

        $this->asCentral();

        $this->actingAs($user)
            ->get($this->tenantUrl('/ar/admin/products'))
            ->assertRedirect($this->tenantUrl('/ar'));
    }

    public function test_customer_cannot_write_to_an_admin_route(): void
    {
        $user = $this->userWithRoles(UserRole::CUSTOMER->value);

        $this->asCentral();

        $this->actingAs($user)
            ->put($this->tenantUrl('/ar/admin/settings'), ['site_name' => 'Hijacked'])
            ->assertRedirect($this->tenantUrl('/ar'));

        tenancy()->initialize($this->tenant);
        $this->assertNotSame('Hijacked', app(GeneralSettings::class)->site_name);
    }

    public function test_api_is_tenant_scoped(): void
    {
        $this->asCentral();

        $this->getJson($this->tenantUrl('/api/v1/items/categories'))
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->asCentral();

        $this->getJson('http://localhost/api/v1/items/categories')->assertNotFound();
    }

    public function test_login_page_works_on_store(): void
    {
        $this->asCentral();

        $this->get($this->tenantUrl('/login'))->assertOk();

        $this->asCentral();

        $this->get('http://localhost/login')->assertNotFound();
    }

    public function test_staff_land_on_store_admin_after_login(): void
    {
        $user = $this->userWithRoles(UserRole::SUPER_ADMIN->value);

        $this->asCentral();

        $this->post($this->tenantUrl('/login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($this->tenantUrl('/'.Locales::default().'/admin'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_without_roles_or_permissions_is_sent_to_storefront(): void
    {
        $user = $this->userWithRoles();

        $this->asCentral();

        $this->actingAs($user)->get($this->tenantUrl('/ar/admin'))->assertRedirect($this->tenantUrl('/ar'));
    }

    public function test_customer_with_a_staff_role_reaches_admin(): void
    {
        $user = $this->userWithRoles(UserRole::CUSTOMER->value, UserRole::USER->value);

        $this->asCentral();

        $this->actingAs($user)->get($this->tenantUrl('/ar/admin'))->assertOk();
    }

    public function test_user_with_a_direct_permission_reaches_admin(): void
    {
        $user = $this->userWithRoles(UserRole::CUSTOMER->value);
        $user->givePermissionTo('Product View List');

        $this->asCentral();

        $this->actingAs($user)->get($this->tenantUrl('/ar/admin'))->assertOk();
    }

    public function test_store_user_on_platform_host_gets_no_admin(): void
    {
        $admin = $this->userWithRoles(UserRole::SUPER_ADMIN->value);

        $this->asCentral();
        $this->actingAs($admin)->get('http://localhost/ar/admin')->assertNotFound();
    }

    public function test_a_session_never_crosses_stores_or_the_platform(): void
    {
        $user = $this->userWithRoles(UserRole::SUPER_ADMIN->value);
        $other = $this->secondTenant();

        // The other store's user with the same id (e.g. its owner): what a replayed cookie would log in as.
        $other->run(function () use ($user) {
            User::forceCreate([
                'id' => $user->id,
                'name' => 'Routing twin',
                'email' => 'twin-'.Str::lower(Str::random(8)).'@routing.test',
                'password' => 'password',
                'email_verified_at' => now(),
            ])->assignRole(UserRole::SUPER_ADMIN->value);
        });
        $this->otherUserIds[] = $user->id;

        // A stored session in Redis, as in production, so a cookie can be replayed in a "new process".
        config(['session.driver' => 'redis', 'session.store' => 'sessions']);
        $sessionIds = [];

        try {
            $cookie = $this->logIn($user, $sessionIds);

            // Control: the cookie works on its own store.
            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $cookie)->get($this->tenantUrl('/ar/admin'))->assertOk();

            // Replayed on another store: a guest there.
            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $cookie)->get('http://other.localhost/ar/admin')
                ->assertRedirect('http://other.localhost/login');

            // ...and the session is gone, also for its own store.
            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $cookie)->get($this->tenantUrl('/ar/admin'))
                ->assertRedirect($this->tenantUrl('/login'));

            // Replayed on the platform host: the store session is dropped there too.
            $cookie = $this->logIn($user, $sessionIds);

            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $cookie)->get('http://localhost/ar')->assertOk();

            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $cookie)->get($this->tenantUrl('/ar/admin'))
                ->assertRedirect($this->tenantUrl('/login'));
        } finally {
            foreach ($sessionIds as $id) {
                $this->app['session']->driver()->getHandler()->destroy($id);
            }
        }
    }

    /**
     * Log in through the store's login form; returns the session id the browser would keep.
     *
     * @param  list<string>  $sessionIds
     */
    private function logIn(User $user, array &$sessionIds): string
    {
        $this->freshProcess();

        $login = $this->post($this->tenantUrl('/login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $sessionIds[] = $id = $login->getCookie(config('session.cookie'))->getValue();

        return $id;
    }

    /**
     * What a new PHP process sees: no tenancy, no resolved user, no session in memory.
     */
    private function freshProcess(): void
    {
        $this->asCentral();
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    /**
     * Sets GeneralSettings::site_name per store straight in its database.
     *
     * @param  array<string, string>  $names  store id => site name
     * @return array<string, string> the previous names
     */
    private function siteNames(array $names): array
    {
        $previous = [];

        tenancy()->runForMultiple(array_keys($names), function () use ($names, &$previous) {
            $row = DB::table('settings')->where('group', 'general')->where('name', 'site_name');
            $previous[tenant('id')] = json_decode($row->value('payload'));
            $row->update(['payload' => json_encode($names[tenant('id')])]);
        });

        return $previous;
    }

    private function userWithRoles(string ...$roles): User
    {
        $user = User::forceCreate([
            'name' => 'Routing '.(implode(' ', $roles) ?: 'no role'),
            'email' => 'user-'.Str::lower(Str::random(12)).'@routing.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($roles);
        $this->userIds[] = $user->id;

        return $user;
    }
}
