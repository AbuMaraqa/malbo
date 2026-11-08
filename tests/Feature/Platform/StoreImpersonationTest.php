<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Tests\TenantTestCase;

class StoreImpersonationTest extends TenantTestCase
{
    public function test_token_signs_the_user_in_and_redirects_to_admin(): void
    {
        $user = $this->superAdmin();
        $token = tenancy()->impersonate($this->tenant, (string) $user->id, '/ar/admin', 'web');

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertRedirect('/ar/admin');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_owner_stays_signed_in_on_the_next_request(): void
    {
        $token = tenancy()->impersonate($this->tenant, (string) $this->superAdmin()->id, '/ar/admin', 'web');

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertRedirect('/ar/admin');
        auth()->forgetGuards(); // the next request reads the login from the session only

        $this->get($this->tenantUrl('/ar/admin'))->assertOk();
    }

    public function test_token_is_single_use(): void
    {
        $token = tenancy()->impersonate($this->tenant, (string) $this->superAdmin()->id, '/ar/admin', 'web');

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertRedirect();
        auth('web')->logout();

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertNotFound();
        $this->assertGuest('web');
    }

    public function test_expired_token_is_refused(): void
    {
        $token = tenancy()->impersonate($this->tenant, (string) $this->superAdmin()->id, '/ar/admin', 'web');
        $token->forceFill(['created_at' => now()->subSeconds(61)])->save();

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertForbidden();

        $this->assertGuest('web');
        ImpersonationToken::whereKey($token->token)->delete();
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get($this->tenantUrl('/impersonate/no-such-token'))->assertNotFound();
    }

    public function test_token_consumed_by_a_concurrent_request_signs_no_one_in(): void
    {
        $token = tenancy()->impersonate($this->tenant, (string) $this->superAdmin()->id, '/ar/admin', 'web');

        // Another request with the same link uses it right after this one read it.
        ImpersonationToken::retrieved(fn (ImpersonationToken $read) => ImpersonationToken::whereKey($read->token)->delete());

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertNotFound();
        $this->assertGuest('web');
    }

    public function test_token_dated_in_the_future_is_refused(): void
    {
        $token = tenancy()->impersonate($this->tenant, (string) $this->superAdmin()->id, '/ar/admin', 'web');
        $token->forceFill(['created_at' => now()->addMinutes(10)])->save();

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertForbidden();

        $this->assertGuest('web');
        ImpersonationToken::whereKey($token->token)->delete();
    }

    public function test_token_for_a_missing_user_is_not_found_and_consumed(): void
    {
        $token = tenancy()->impersonate($this->tenant, '999999999', '/ar/admin', 'web');

        $this->get($this->tenantUrl('/impersonate/'.$token->token))->assertNotFound();

        $this->assertGuest('web');
        $this->assertNull(ImpersonationToken::find($token->token));
    }

    public function test_previous_session_data_is_dropped(): void
    {
        $token = tenancy()->impersonate($this->tenant, (string) $this->superAdmin()->id, '/ar/admin', 'web');

        $this->withSession(['left_over' => 'from before'])
            ->get($this->tenantUrl('/impersonate/'.$token->token))
            ->assertRedirect('/ar/admin')
            ->assertSessionMissing('left_over');
    }

    private function superAdmin(): User
    {
        return User::forceCreate(['name' => 'Owner', 'email' => 'owner-'.Str::random(12).'@impersonation.test', 'password' => 'password'])->assignRole('Super Admin');
    }
}
