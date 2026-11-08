<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Concerns\CreatesTenants;
use Tests\Support\TestingDatabases;

/**
 * Tests that run inside the store "testing" (domain testing.localhost).
 *
 * The central testing database is migrated once per process. The "testing" store is
 * provisioned once and reused, also across test runs: its database carries a fingerprint
 * of the tenant migrations and seeders and is re-adopted while that still matches.
 * Each test runs with tenancy initialized and, by default, inside a transaction on the
 * `tenant` connection that is rolled back afterwards.
 *
 * Initializing another tenant (or re-initializing) purges the `tenant` connection, which
 * silently drops that transaction. Tests that call secondTenant() or switch tenants must
 * set $tenantTransactions = false and clean up their own data. A lost transaction fails
 * the test and the "testing" database is dropped so no later test inherits its writes.
 */
abstract class TenantTestCase extends TestCase
{
    use CreatesTenants;

    protected bool $tenantTransactions = true;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTestingDatabases();

        TestingDatabases::migrateCentralOnce();

        $this->tenant = $this->reusableTenant('testing');

        tenancy()->initialize($this->tenant);

        if ($this->tenantTransactions) {
            DB::connection('tenant')->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        $lost = null;

        if ($this->app) {
            try {
                $this->assertTenantTransactionIntact();
            } catch (AssertionFailedError $e) {
                $lost = $e;
            }

            $this->rollBackTenantTransactions();
            tenancy()->end();

            if ($lost) {
                // Writes made after the loss were committed: never reuse this database.
                $this->destroyTenant('testing');
            }
        }

        parent::tearDown();

        if ($lost) {
            throw $lost;
        }
    }

    /**
     * A second store ("other", domain other.localhost), created on first use and
     * reused like "testing". Does not initialize it.
     */
    protected function secondTenant(): Tenant
    {
        if ($this->tenantTransactions) {
            // Provisioning (or later initializing) "other" re-initializes tenancy and
            // would drop the test transaction before anything was written.
            $this->fail('secondTenant() needs $tenantTransactions = false.');
        }

        return $this->reusableTenant('other');
    }

    /**
     * Leave the store context, e.g. before requesting the platform host.
     * Ends (rolls back) the test's tenant transaction.
     */
    protected function asCentral(): void
    {
        $this->rollBackTenantTransactions();
        tenancy()->end();
    }

    protected function tenantUrl(string $path): string
    {
        return 'http://testing.'.config('tenancy.base_domain').$path;
    }

    /**
     * Fails when the test runs with tenant transactions but the `tenant` connection has
     * none open any more, i.e. tenancy was re-initialized and later writes were committed.
     */
    protected function assertTenantTransactionIntact(): void
    {
        if ($this->tenantTransactions && tenancy()->initialized
            && DB::connection('tenant')->transactionLevel() === 0) {
            $this->fail('Tenant transaction was lost (tenancy was re-initialized); set $tenantTransactions = false.');
        }
    }

    private function reusableTenant(string $id): Tenant
    {
        $tenant = Tenant::find($id);

        if ($tenant && TestingDatabases::isVerified($id)) {
            return $tenant;
        }

        $database = $this->tenantDatabaseName($id);

        if ($this->storedFingerprint($database) === TestingDatabases::fingerprint()) {
            // Central tables were reset (or this is a new run): re-adopt the database
            // without TenantCreated, so nothing is created, migrated or seeded again.
            $tenant ??= $this->adoptTenant($id);
        } else {
            $this->destroyTenant($id);
            $tenant = $this->provisionTenant($id, $this->reusableTenantAttributes($id));
            $this->storeFingerprint($database);
        }

        TestingDatabases::markVerified($id);

        return $tenant;
    }

    private function adoptTenant(string $id): Tenant
    {
        $tenant = Tenant::withoutEvents(
            fn () => Tenant::create(['id' => $id, ...$this->reusableTenantAttributes($id)])
        );
        $tenant->domains()->create(['domain' => $id.'.'.config('tenancy.base_domain')]);

        return $tenant;
    }

    private function reusableTenantAttributes(string $id): array
    {
        return ['name' => ['ar' => $id, 'en' => $id]];
    }

    private function storedFingerprint(string $database): ?string
    {
        if (! $this->databaseExists($database)) {
            return null;
        }

        try {
            return $this->onDatabase($database, fn ($connection) => $connection->table('_test_fingerprint')->value('fingerprint'));
        } catch (QueryException) {
            return null; // provisioned before fingerprints existed
        }
    }

    private function storeFingerprint(string $database): void
    {
        $this->ensureTestingDatabases();

        $this->onDatabase($database, function ($connection): void {
            $connection->statement('CREATE TABLE IF NOT EXISTS _test_fingerprint (fingerprint CHAR(64) NOT NULL)');
            $connection->table('_test_fingerprint')->delete();
            $connection->table('_test_fingerprint')->insert(['fingerprint' => TestingDatabases::fingerprint()]);
        });
    }

    /**
     * PostgreSQL has no cross-database queries: open a short connection to that database.
     */
    private function onDatabase(string $database, callable $callback): mixed
    {
        config(['database.connections.test_fingerprint' => [...config('database.connections.central'), 'database' => $database]]);

        try {
            return $callback(DB::connection('test_fingerprint'));
        } finally {
            DB::purge('test_fingerprint');
        }
    }

    private function rollBackTenantTransactions(): void
    {
        if (! $this->tenantTransactions || ! tenancy()->initialized) {
            return;
        }

        $connection = DB::connection('tenant');

        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
}
