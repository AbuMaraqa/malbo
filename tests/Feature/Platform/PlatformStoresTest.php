<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Platform\StoreOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Core\Entities\User;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Tests\CentralTestCase;

class PlatformStoresTest extends CentralTestCase
{
    private const DASHBOARD = 'http://localhost/ar/platform';

    private const KEPT_DATABASE = 'test_import_keep_f';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(PlatformAdmin::create(['name' => 'P', 'email' => 'p@platform.test', 'password' => 'secret-pass']), 'platform');
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            DB::connection('central')->statement('DROP DATABASE IF EXISTS "'.self::KEPT_DATABASE.'" WITH (FORCE)');
        }

        parent::tearDown();
    }

    public function test_dashboard_renders_for_admins(): void
    {
        $this->store('acme');

        $this->get(self::DASHBOARD)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Platform/Stores')
            ->where('table.data.0.domain', 'acme.localhost')
            ->where('platformAdmin.name', 'P')
            ->where('storesUrl', self::DASHBOARD.'/stores'));
    }

    public function test_the_admin_gets_the_texts_of_the_tables(): void
    {
        $this->get(self::DASHBOARD)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('translations.Stores', 'المتاجر')
            ->has('translations.Actions'));
    }

    public function test_every_action_refuses_guests(): void
    {
        $this->store('acme');
        auth('platform')->logout();

        $this->get(self::DASHBOARD)->assertRedirect(self::DASHBOARD.'/login');
        $this->patch(self::DASHBOARD.'/stores/acme/status', ['status' => 'suspended'])->assertRedirect(self::DASHBOARD.'/login');
        $this->post(self::DASHBOARD.'/stores/acme/open-admin')->assertRedirect(self::DASHBOARD.'/login');
        $this->delete(self::DASHBOARD.'/stores/acme', ['confirm' => 'acme'])->assertRedirect(self::DASHBOARD.'/login');

        $this->assertSame(Tenant::STATUS_ACTIVE, Tenant::find('acme')->status);
    }

    public function test_a_store_user_is_not_a_platform_admin(): void
    {
        $this->store('acme');
        auth('platform')->logout();

        // The `web` guard is a store's: it opens nothing on the platform.
        $this->actingAs(new User(['name' => 'x', 'email' => 'x@x.test']), 'web');

        $this->delete(self::DASHBOARD.'/stores/acme', ['confirm' => 'acme'])->assertRedirect(self::DASHBOARD.'/login');
        $this->assertNotNull(Tenant::find('acme'));
    }

    public function test_open_admin_is_refused_for_a_suspended_store(): void
    {
        $this->store('off', ['status' => Tenant::STATUS_SUSPENDED]);

        $this->from(self::DASHBOARD)->post(self::DASHBOARD.'/stores/off/open-admin')
            ->assertRedirect(self::DASHBOARD)
            ->assertSessionHas('error');

        $this->assertSame(0, ImpersonationToken::where('tenant_id', 'off')->count());
    }

    public function test_counters(): void
    {
        $this->store('one');
        $this->store('two');
        $this->store('old');
        // Not through the model: VirtualColumn would move a changed created_at into `data`.
        DB::connection('central')->table('tenants')->where('id', 'old')->update(['created_at' => now()->subMonthNoOverflow()->startOfMonth()]);
        $this->store('off', ['status' => Tenant::STATUS_SUSPENDED]);

        $this->get(self::DASHBOARD)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts', ['total' => 4, 'active' => 3, 'suspended' => 1, 'createdThisMonth' => 3]));
    }

    public function test_lists_and_searches_stores(): void
    {
        $this->store('acme', ['owner_email' => 'owner@acme.ps']);
        $this->store('other-shop', ['name' => ['ar' => 'متجر آخر', 'en' => 'Other shop']]);

        $this->assertSame(['acme', 'other-shop'], $this->listed());
        $this->assertSame(['acme'], $this->listed(['filter' => ['search' => 'OWNER@acme']]));
        $this->assertSame(['other-shop'], $this->listed(['filter' => ['search' => 'متجر آخر']]));
        $this->assertSame(['other-shop'], $this->listed(['filter' => ['search' => 'other SHOP']]));
    }

    public function test_filters(): void
    {
        $this->store('acme', ['business_type' => 'fashion']);
        $this->store('off', ['status' => Tenant::STATUS_SUSPENDED]);
        $this->store('old');
        DB::connection('central')->table('tenants')->where('id', 'old')->update(['created_at' => '2020-01-15 10:00:00']);

        $this->assertSame(['off'], $this->listed(['filter' => ['status' => 'suspended']]));
        $this->assertSame(['acme'], $this->listed(['filter' => ['business_type' => 'fashion']]));
        $this->assertSame(['old'], $this->listed(['filter' => ['created_at_to' => '2020-12-31']]));
        $this->assertSame(['acme', 'off'], $this->listed(['filter' => ['created_at_from' => '2021-01-01']]));
    }

    public function test_table_reads_domains_once(): void
    {
        foreach (['one', 'two', 'three'] as $id) {
            $this->store($id);
        }

        DB::connection('central')->enableQueryLog();
        $this->get(self::DASHBOARD)->assertOk();
        $domainQueries = collect(DB::connection('central')->getQueryLog())->filter(fn (array $query) => str_contains($query['query'], '"domains"'));
        DB::connection('central')->disableQueryLog();

        $this->assertCount(1, $domainQueries);
    }

    public function test_suspend_and_activate(): void
    {
        $acme = $this->store('acme');

        $this->from(self::DASHBOARD)->patch(self::DASHBOARD.'/stores/acme/status', ['status' => 'suspended'])
            ->assertRedirect(self::DASHBOARD)
            ->assertSessionHas('success');
        $this->assertSame(Tenant::STATUS_SUSPENDED, $acme->fresh()->status);

        $this->patch(self::DASHBOARD.'/stores/acme/status', ['status' => 'active'])->assertSessionHas('success');
        $this->assertSame(Tenant::STATUS_ACTIVE, $acme->fresh()->status);

        $this->patch(self::DASHBOARD.'/stores/acme/status', ['status' => 'deleted'])->assertSessionHasErrors('status');
        $this->assertSame(Tenant::STATUS_ACTIVE, $acme->fresh()->status);
    }

    public function test_open_store_links_to_the_storefront(): void
    {
        $this->store('acme');

        $this->get(self::DASHBOARD)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('table.data.0.store_url', 'http://acme.localhost/ar'));
    }

    public function test_an_unknown_store_is_404(): void
    {
        $this->patch(self::DASHBOARD.'/stores/nope/status', ['status' => 'active'])->assertNotFound();
    }

    public function test_delete_needs_the_typed_slug_and_drops_the_database(): void
    {
        $this->createTenant('gone');

        $this->delete(self::DASHBOARD.'/stores/gone', ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
        $this->delete(self::DASHBOARD.'/stores/gone')->assertSessionHasErrors('confirm');
        $this->assertNotNull(Tenant::find('gone'));

        $this->delete(self::DASHBOARD.'/stores/gone', ['confirm' => 'gone'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertNull(Tenant::find('gone'));
        $this->assertFalse($this->databaseExists($this->tenantDatabaseName('gone')));
    }

    public function test_deleting_an_imported_store_keeps_its_database(): void
    {
        DB::connection('central')->statement('CREATE DATABASE "'.self::KEPT_DATABASE.'"');

        Tenant::withoutEvents(function () {
            $tenant = new Tenant;
            $tenant->forceFill(['id' => 'keep', 'status' => Tenant::STATUS_ACTIVE]);
            $tenant->setInternal('db_name', self::KEPT_DATABASE);
            $tenant->setInternal('create_database', false);
            $tenant->setInternal('imported', true);
            $tenant->save();

            $tenant->domains()->create(['domain' => 'keep.localhost']);
        });

        $this->delete(self::DASHBOARD.'/stores/keep', ['confirm' => 'keep'])->assertSessionHasNoErrors();

        $this->assertNull(Tenant::find('keep'));
        $this->assertTrue($this->databaseExists(self::KEPT_DATABASE));
    }

    public function test_open_admin_signs_in_as_the_owner_or_the_first_super_admin(): void
    {
        $tenant = $this->createTenant('own');

        [$first, $owner] = $tenant->run(fn () => [$this->superAdmin(), $this->superAdmin()]);

        // No recorded owner: the first Super Admin is used.
        $this->assertSame($first->id, StoreOwner::userId($tenant));

        $tenant->update(['owner_user_id' => $owner->id]);
        $this->assertSame($owner->id, StoreOwner::userId($tenant));

        $redirect = $this->post(self::DASHBOARD.'/stores/own/open-admin')->assertRedirect();
        $this->assertStringStartsWith('http://own.localhost/impersonate/', (string) $redirect->headers->get('Location'));

        $this->assertSame((string) $owner->id, (string) ImpersonationToken::where('tenant_id', 'own')->latest('created_at')->value('user_id'));

        // From the page itself: Inertia leaves for the store's host with a full load.
        $this->withHeaders(['X-Inertia' => 'true'])->post(self::DASHBOARD.'/stores/own/open-admin')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
        $this->flushHeaders();

        // No Super Admin at all: nobody to sign in as.
        $tenant->run(fn () => User::query()->get()->each->delete());
        $tenant->update(['owner_user_id' => null]);
        $this->assertNull(StoreOwner::userId($tenant));

        $tokens = ImpersonationToken::where('tenant_id', 'own')->count();

        $this->from(self::DASHBOARD)->post(self::DASHBOARD.'/stores/own/open-admin')
            ->assertRedirect(self::DASHBOARD)
            ->assertSessionHas('error', __('This store has no owner account to sign in as.'));

        $this->assertSame($tokens, ImpersonationToken::where('tenant_id', 'own')->count());
    }

    /**
     * @return list<string> the store ids a table request lists, sorted
     */
    private function listed(array $query = []): array
    {
        $ids = [];

        $this->get(self::DASHBOARD.'?'.http_build_query($query))->assertOk()->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['table']['data'], 'id');
        });

        sort($ids);

        return $ids;
    }

    private function store(string $id, array $attributes = []): Tenant
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => $id,
            'name' => ['ar' => 'متجر '.$id, 'en' => 'Store '.$id],
            'owner_name' => 'Owner '.$id,
            ...$attributes,
        ]));
        $tenant->domains()->create(['domain' => $id.'.localhost']);

        return $tenant;
    }

    private function superAdmin(): User
    {
        return User::forceCreate(['name' => 'Owner', 'email' => 'owner-'.Str::random(12).'@stores.test', 'password' => 'password'])
            ->assignRole('Super Admin');
    }
}
