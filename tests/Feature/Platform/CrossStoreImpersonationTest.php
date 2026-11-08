<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Tests\TenantTestCase;

class CrossStoreImpersonationTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    public function test_token_of_one_store_is_refused_on_another(): void
    {
        $other = $this->secondTenant(); // provisioning re-initializes tenancy
        tenancy()->initialize($this->tenant);

        $user = User::forceCreate(['name' => 'Owner', 'email' => 'owner-'.Str::random(12).'@impersonation.test', 'password' => 'password'])->assignRole('Super Admin');

        try {
            $token = tenancy()->impersonate($this->tenant, (string) $user->id, '/ar/admin', 'web');

            $this->get('http://'.$other->primaryDomain().'/impersonate/'.$token->token)->assertForbidden();
            $this->assertGuest('web');
        } finally {
            tenancy()->initialize($this->tenant);
            $user->forceDelete();
            ImpersonationToken::where('user_id', (string) $user->id)->delete();
        }
    }
}
