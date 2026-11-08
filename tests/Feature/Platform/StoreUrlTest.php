<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Platform\StoreUrl;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Tests\CentralTestCase;

class StoreUrlTest extends CentralTestCase
{
    public function test_impersonation_link_points_at_the_store_and_its_admin(): void
    {
        $tenant = $this->store();

        $url = StoreUrl::impersonation($tenant, 7, 'en');

        $this->assertStringStartsWith('http://acme.localhost:8000/impersonate/', $url);
        $token = ImpersonationToken::findOrFail(basename($url));
        $this->assertSame('acme', $token->tenant_id);
        $this->assertSame('7', (string) $token->user_id);
        $this->assertSame('/en/admin', $token->redirect_url);
        $this->assertSame('web', $token->auth_guard);
    }

    public function test_impersonation_link_only_redirects_to_a_supported_locale(): void
    {
        foreach (['/evil.com', '//evil.com', 'fr', ''] as $locale) {
            $url = StoreUrl::impersonation($this->store(), 7, $locale);

            $this->assertSame('/ar/admin', ImpersonationToken::findOrFail(basename($url))->redirect_url, $locale);
        }
    }

    private function store(): Tenant
    {
        $this->app->instance('request', Request::create('http://localhost:8000/ar/platform'));

        if ($tenant = Tenant::find('acme')) {
            return $tenant;
        }

        $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'acme']));
        $tenant->domains()->create(['domain' => 'acme.localhost']);

        return $tenant;
    }
}
