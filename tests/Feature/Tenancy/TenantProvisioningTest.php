<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Tenancy\StorageLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Events\DeletingTenant;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Tests\CentralTestCase;
use Tests\Concerns\CreatesTenants;
use Tests\Feature\Tenancy\Fixtures\FailingSeeder;

class TenantProvisioningTest extends CentralTestCase
{
    use CreatesTenants;

    public function test_creating_a_tenant_creates_migrates_and_seeds_its_database(): void
    {
        $tenant = $this->createTenant('acme');
        $this->assertSame(config('tenancy.database.prefix').'acme', $tenant->database()->getName());
        $tenant->run(function () {
            $this->assertTrue(Schema::hasTable('items_products'));
            $this->assertTrue(Schema::hasTable('settings'));
            $this->assertTrue(Role::where('name', 'Super Admin')->exists());
            $this->assertTrue(Role::where('name', 'Customer')->exists());
            $this->assertTrue(Permission::where('name', 'Product View List')->exists());
            $this->assertGreaterThan(0, Currency::count());
            $this->assertSame(0, User::count());
        });
        $this->assertSame('acme.localhost', $tenant->domains()->first()->domain);
        $this->assertTrue(StorageLink::isLink(public_path('public-acme')), 'Provisioning links the store public disk.');
    }

    public function test_deleting_a_tenant_drops_its_database(): void
    {
        $tenant = $this->createTenant('gone');
        $this->assertTrue(StorageLink::isLink(public_path('public-gone')));
        $tenant->run(fn () => Storage::disk('items')->put('1/x.jpg', 'gone-file'));
        $storage = storage_path('tenantgone');
        $this->assertDirectoryExists($storage);

        $tenant->delete();

        $this->assertFalse($this->databaseExists(config('tenancy.database.prefix').'gone'));
        clearstatcache();
        $this->assertFalse(StorageLink::isLink(public_path('public-gone')), 'Deleting a store removes its storage link.');
        $this->assertDirectoryDoesNotExist($storage, 'A new store with the same id must not inherit the files.');
        $archived = glob(storage_path('deleted-stores/tenantgone-*'), GLOB_ONLYDIR);
        $this->assertCount(1, $archived);
        $this->assertSame('gone-file', file_get_contents($archived[0].'/app/public/items/1/x.jpg'));
    }

    public function test_failed_seed_rolls_back_tenant_and_database(): void
    {
        config(['tenancy.seeder_parameters.--class' => FailingSeeder::class]);
        try {
            Tenant::create(['id' => 'broken']);
            $this->fail('The failing seeder must make provisioning throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Seeding failed on purpose.', $e->getMessage());
        }
        $this->assertNull(Tenant::find('broken'));
        $this->assertFalse($this->databaseExists(config('tenancy.database.prefix').'broken'));
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_a_failing_clean_up_is_reported_and_the_original_error_rethrown(): void
    {
        Exceptions::fake();
        config(['tenancy.migration_parameters.--path' => [__DIR__.'/Fixtures/failing-migrations']]);
        Event::listen(DeletingTenant::class, fn () => throw new \LogicException('Clean-up failed on purpose.'));
        $this->beforeApplicationDestroyed(function () {
            Event::forget(DeletingTenant::class);
            $this->destroyTenant('cleanup');
        });

        try {
            Tenant::create(['id' => 'cleanup']);
            $this->fail('The failing migration must make provisioning throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Migration failed on purpose.', $e->getMessage());
        }

        Exceptions::assertReported(fn (\LogicException $e) => $e->getMessage() === 'Clean-up failed on purpose.');
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_failed_provisioning_never_drops_a_database_it_did_not_create(): void
    {
        $this->createDatabase(config('tenancy.database.prefix').'taken');
        $this->beforeApplicationDestroyed(fn () => $this->dropTenantDatabase(config('tenancy.database.prefix').'taken'));

        try {
            Tenant::create(['id' => 'taken']);
            $this->fail('Creating a tenant over an existing database must throw.');
        } catch (TenantDatabaseAlreadyExistsException) {
        }

        $this->assertNull(Tenant::find('taken'));
        $this->assertTrue($this->databaseExists(config('tenancy.database.prefix').'taken'));
    }
}
