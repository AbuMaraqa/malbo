<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * Platform routes are bound to the central domains, so a store's host never answers them, not
 * even for a signed-in platform admin. In `core` this was a guard inside each Livewire component,
 * because Livewire updates posted to one shared route; controllers have no such route.
 */
class PlatformComponentsOnStoreHostTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    public function test_login_does_not_exist_on_a_store_host(): void
    {
        $this->asCentral();

        $this->get($this->tenantUrl('/ar/platform/login'))->assertNotFound();

        $this->asCentral();

        $this->assertRefused($this->post($this->tenantUrl('/ar/platform/login'), ['email' => 'a@p.test', 'password' => 'x']));
    }

    public function test_sign_up_does_not_exist_on_a_store_host(): void
    {
        $this->asCentral();

        $this->get($this->tenantUrl('/ar/register-store'))->assertNotFound();
    }

    public function test_stores_dashboard_does_not_exist_on_a_store_host_even_for_an_admin(): void
    {
        $this->asCentral();
        $admin = PlatformAdmin::create(['name' => 'P', 'email' => 'p-'.Str::random(12).'@host.test', 'password' => 'secret-pass-12']);

        try {
            foreach ([
                ['get', '/ar/platform'],
                ['patch', '/ar/platform/stores/testing/status'],
                ['post', '/ar/platform/stores/testing/open-admin'],
                ['delete', '/ar/platform/stores/testing'],
            ] as [$method, $path]) {
                $this->asCentral();

                $this->assertRefused($this->actingAs($admin, 'platform')->{$method}($this->tenantUrl($path)));
            }
        } finally {
            tenancy()->end();
            $admin->delete(); // central: not inside the tenant transaction
        }

        $this->assertTrue(Tenant::find('testing')->isActive());
    }

    /**
     * 404, or 405 where a store page has the same path for GET only: no platform route answered.
     */
    private function assertRefused(TestResponse $response): void
    {
        $this->assertContains($response->getStatusCode(), [404, 405]);
    }
}
