<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenants;
use Tests\Support\TestingDatabases;

/**
 * Tests that run against the central (platform) database.
 *
 * No RefreshDatabase: CREATE DATABASE cannot run inside a transaction on PostgreSQL. Instead the
 * central testing database is migrated once per process, and before every test all
 * test tenant databases (except the reusable stores' ones, see TestingDatabases) are
 * dropped and the central tables are emptied.
 */
abstract class CentralTestCase extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTestingDatabases();

        TestingDatabases::migrateCentralOnce();

        $this->resetCentralDatabase();
    }

    private function resetCentralDatabase(): void
    {
        $central = DB::connection('central');
        $prefix = (string) config('tenancy.database.prefix');

        $reusable = array_map(fn (string $id) => $this->tenantDatabaseName($id), TestingDatabases::REUSABLE_TENANTS);

        $databases = $central->table('pg_database')->whereLike('datname', addcslashes($prefix, '_%').'%')->pluck('datname');

        foreach ($databases as $database) {
            if (! in_array($database, $reusable, true)) {
                $this->dropTenantDatabase($database);
            }
        }

        $tables = collect(Schema::connection('central')->getTables())
            ->pluck('name')
            ->reject(fn (string $table) => $table === 'migrations')
            ->map(fn (string $table) => '"'.$table.'"')
            ->implode(', ');

        if ($tables !== '') {
            $central->statement("TRUNCATE {$tables} RESTART IDENTITY CASCADE");
        }
    }
}
