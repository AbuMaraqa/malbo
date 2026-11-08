<?php

namespace Tests\Feature\Platform;

use App\Http\Middleware\ScopeSessionToStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Tests\TenantTestCase;

/**
 * Deleting a store frees its slug; a new store may take it. Sessions of the old store must
 * not sign anyone into the new one (its user 1 is usually the new owner).
 */
class RecreatedStoreSessionTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    public function test_session_of_a_deleted_store_does_not_open_a_new_store_with_the_same_slug(): void
    {
        $user = User::forceCreate([
            'name' => 'Old owner',
            'email' => 'old-'.Str::lower(Str::random(8)).'@recreated.test',
            'password' => 'password',
        ])->assignRole(UserRole::SUPER_ADMIN->value);

        // Stored in Redis, as in production, so the cookie can be replayed in a "new process".
        config(['session.driver' => 'redis', 'session.store' => 'sessions']);
        $createdAt = DB::connection('central')->table('tenants')->where('id', 'testing')->value('created_at');
        $sessionId = null;

        try {
            $this->freshProcess();
            $login = $this->post($this->tenantUrl('/login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
            $sessionId = $login->getCookie(config('session.cookie'))->getValue();

            // Control: the session works on its store.
            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $sessionId)->get($this->tenantUrl('/ar/admin'))->assertOk();

            // The store is deleted and a new one takes the slug: same id, created later.
            DB::connection('central')->table('tenants')->where('id', 'testing')->update(['created_at' => now()->addMinute()]);

            $this->freshProcess();
            $this->withCookie(config('session.cookie'), $sessionId)->get($this->tenantUrl('/ar/admin'))
                ->assertRedirect($this->tenantUrl('/login'));
        } finally {
            DB::connection('central')->table('tenants')->where('id', 'testing')->update(['created_at' => $createdAt]);
            if ($sessionId !== null) {
                $this->app['session']->driver()->getHandler()->destroy($sessionId);
            }
            tenancy()->initialize($this->tenant->fresh());
            $user->delete();
        }
    }

    public function test_scope_names_the_store_and_its_creation(): void
    {
        $scope = ScopeSessionToStore::scopeFor($this->tenant->fresh());

        $this->assertStringStartsWith('testing:', $scope);
        $this->assertNotSame('testing:', $scope);
    }

    /**
     * What a new PHP process sees: no tenancy, no resolved user, no session in memory.
     */
    private function freshProcess(): void
    {
        $this->asCentral();
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
