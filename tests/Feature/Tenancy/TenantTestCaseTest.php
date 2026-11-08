<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class TenantTestCaseTest extends TenantTestCase
{
    public function test_each_test_runs_inside_the_testing_store(): void
    {
        $this->assertTrue(tenancy()->initialized);
        $this->assertSame('testing', tenant('id'));
        $this->assertSame('tenant', DB::getDefaultConnection());
        $this->assertSame(config('tenancy.database.prefix').'testing', DB::connection()->getDatabaseName());
        $this->assertSame('testing.localhost', $this->tenant->primaryDomain());
        $this->assertSame('http://testing.localhost/en/cart', $this->tenantUrl('/en/cart'));
    }

    public function test_writes_are_made_inside_a_transaction(): void
    {
        Role::findOrCreate('Harness Probe');

        $this->assertSame(1, DB::connection('tenant')->transactionLevel());
        $this->assertTrue(Role::where('name', 'Harness Probe')->exists());
    }

    /** Runs after the previous test: its write must have been rolled back. */
    public function test_writes_of_the_previous_test_were_rolled_back(): void
    {
        $this->assertFalse(Role::where('name', 'Harness Probe')->exists());
    }

    public function test_a_lost_tenant_transaction_fails_the_test(): void
    {
        tenancy()->end();
        tenancy()->initialize($this->tenant); // purges the `tenant` connection

        try {
            $this->assertTenantTransactionIntact();
            $this->fail('The lost-transaction guard did not trip.');
        } catch (AssertionFailedError $e) {
            $this->assertStringStartsWith('Tenant transaction was lost', $e->getMessage());
        }

        // Nothing was written; restore a transaction so tearDown's guard passes.
        DB::connection('tenant')->beginTransaction();
    }

    public function test_second_tenant_refuses_to_run_inside_a_tenant_transaction(): void
    {
        try {
            $this->secondTenant();
            $this->fail('secondTenant() must refuse a transactional test.');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('$tenantTransactions = false', $e->getMessage());
        }

        $this->assertSame(1, DB::connection('tenant')->transactionLevel());
    }

    public function test_as_central_ends_tenancy(): void
    {
        $this->asCentral();

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('central', DB::getDefaultConnection());
        $this->assertSame(config('database.connections.central.database'), DB::connection()->getDatabaseName());
    }
}
