<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Tests\TenantTestCase;

/**
 * Tokens live in the store's own database. A token of one store opens nothing in another, even
 * when the other store has a token with the same id and a user with the same id.
 */
class ApiTokenIsolationTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    public function test_a_token_of_one_store_is_refused_by_another(): void
    {
        $other = $this->secondTenant();
        $mine = $this->superAdmin();
        $token = $mine->createToken('mine');

        // The same token id in the other store, with its own secret.
        $theirs = $other->run(function () use ($mine, $token) {
            $user = $this->superAdmin();
            $user->tokens()->forceCreate([
                'id' => $token->accessToken->id,
                'name' => 'theirs',
                'token' => hash('sha256', 'their-secret'),
                'abilities' => ['*'],
            ]);

            return $user;
        });

        try {
            $this->asCentral();
            $this->getJson($this->tenantUrl('/api/v1/items/orders'), ['Authorization' => 'Bearer '.$token->plainTextToken])->assertOk();

            $this->asCentral();
            $this->app['auth']->forgetGuards();
            $this->getJson('http://other.localhost/api/v1/items/orders', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        } finally {
            tenancy()->end();
            $other->run(function () use ($theirs) {
                $theirs->tokens()->delete();
                $theirs->delete();
            });
            tenancy()->initialize($this->tenant);
            $mine->tokens()->delete();
            $mine->delete();
        }
    }

    private function superAdmin(): User
    {
        return User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin-'.Str::lower(Str::random(12)).'@tokens.test',
            'password' => 'password',
        ])->assignRole('Super Admin');
    }
}
