<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use App\Tenancy\Jobs\ArchiveTenantStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait CreatesTenants
{
    /**
     * Create a store (database created, migrated and seeded) with the domain
     * "{id}.{base domain}". It is deleted, and its database dropped, when the test ends.
     */
    protected function createTenant(string $id, array $attributes = []): Tenant
    {
        $tenant = $this->provisionTenant($id, $attributes);

        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant($id));

        return $tenant;
    }

    /**
     * Create a store without registering its clean-up.
     */
    protected function provisionTenant(string $id, array $attributes = []): Tenant
    {
        $this->ensureTestingDatabases();

        // A database left behind by an aborted run would make CreateDatabase fail.
        if (! Tenant::whereKey($id)->exists()) {
            $this->dropTenantDatabase($this->tenantDatabaseName($id));
        }

        $tenant = Tenant::create(['id' => $id, ...$attributes]);
        $tenant->domains()->create(['domain' => $id.'.'.config('tenancy.base_domain')]);

        return $tenant;
    }

    protected function destroyTenant(string $id): void
    {
        $this->ensureTestingDatabases();

        if (tenancy()->initialized && tenant('id') === $id) {
            tenancy()->end();
        }

        $tenant = Tenant::find($id);
        $database = $tenant?->database()->getName() ?? $this->tenantDatabaseName($id);

        if ($tenant && $tenant->database()->manager()->databaseExists($database)) {
            $tenant->delete(); // TenantDeleted -> DeleteDatabase, ArchiveTenantStorage
        } else {
            $tenant?->deleteQuietly();
            $this->dropTenantDatabase($database);
        }

        $this->deleteArchivedStorage($id);
    }

    /**
     * Test stores are not kept: drop what ArchiveTenantStorage moved to storage/deleted-stores.
     */
    protected function deleteArchivedStorage(string $id): void
    {
        $archive = app(FilesystemTenancyBootstrapper::class)->centralStoragePath().'/'.ArchiveTenantStorage::ARCHIVE_DIRECTORY;
        $prefix = config('tenancy.filesystem.suffix_base').$id.'-';

        foreach (glob($archive.'/'.$prefix.'*', GLOB_ONLYDIR) ?: [] as $directory) {
            File::deleteDirectory($directory);
        }
    }

    protected function tenantDatabaseName(string $id): string
    {
        return config('tenancy.database.prefix').$id.config('tenancy.database.suffix');
    }

    protected function databaseExists(string $database): bool
    {
        return DB::connection('central')->table('pg_database')->where('datname', $database)->exists();
    }

    protected function createDatabase(string $database): void
    {
        $this->ensureTestingDatabases();

        DB::connection('central')->statement('CREATE DATABASE "'.$database.'"');
    }

    protected function dropTenantDatabase(string $database): void
    {
        $this->ensureTestingDatabases();

        DB::connection('central')->statement('DROP DATABASE IF EXISTS "'.$database.'" WITH (FORCE)');
    }

    /**
     * Never let the test harness create, wipe or drop anything but testing databases.
     */
    protected function ensureTestingDatabases(): void
    {
        $central = DB::connection('central')->getDatabaseName();
        $prefix = (string) config('tenancy.database.prefix');

        if (! str_ends_with($central, '_testing') || ! str_starts_with($prefix, 'test_')) {
            throw new RuntimeException(
                "Refusing to touch databases: central [{$central}] must end with _testing and the tenant prefix [{$prefix}] must start with test_."
            );
        }
    }
}
