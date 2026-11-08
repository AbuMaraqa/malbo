<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\TenantMigrationPaths;
use Illuminate\Support\Facades\Schema;
use Tests\CentralTestCase;

class CentralSchemaTest extends CentralTestCase
{
    public function test_central_schema_has_only_central_tables(): void
    {
        foreach (['tenants', 'domains', 'tenant_user_impersonation_tokens', 'sessions', 'cache', 'jobs', 'failed_jobs', 'job_batches'] as $t) {
            $this->assertTrue(Schema::connection('central')->hasTable($t), $t);
        }
        foreach (['users', 'settings', 'items_products', 'branch_branches', 'permissions', 'media'] as $t) {
            $this->assertFalse(Schema::connection('central')->hasTable($t), $t);
        }
    }

    public function test_tenant_migration_paths_include_every_enabled_module(): void
    {
        $paths = TenantMigrationPaths::all();
        $this->assertContains(database_path('migrations/tenant'), $paths);
        $this->assertContains(database_path('settings'), $paths);
        foreach (['Core', 'Items', 'Branch', 'GeoLocation'] as $m) {
            $this->assertContains(module_path($m, 'database/migrations'), $paths);
        }
    }
}
