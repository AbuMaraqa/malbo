<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

class SecondTenantTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    public function test_second_tenant_has_its_own_database_and_is_reused(): void
    {
        $other = $this->secondTenant();

        $this->assertSame('other', $other->id);
        $this->assertSame('other.localhost', $other->primaryDomain());
        $this->assertSame($other->created_at->toDateTimeString(), $this->secondTenant()->created_at->toDateTimeString());

        $other->run(function () {
            $this->assertSame(config('tenancy.database.prefix').'other', DB::connection()->getDatabaseName());
        });

        // run() restores the testing store.
        $this->assertSame('testing', tenant('id'));
        $this->assertSame(config('tenancy.database.prefix').'testing', DB::connection()->getDatabaseName());
    }
}
