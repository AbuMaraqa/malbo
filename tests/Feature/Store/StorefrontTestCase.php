<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Services\StorefrontCatalogCache;
use Modules\Store\Theme\ThemeService;
use Tests\TenantTestCase;

/**
 * Store pages inside store `testing`. A test's database writes roll back but Redis keeps what it
 * cached, so the catalog and theme caches start empty.
 */
abstract class StorefrontTestCase extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');
        app(StorefrontCatalogCache::class)->forget();
        app(ThemeService::class)->forget();
    }

    /**
     * The props the page was sent, as the browser reads them.
     *
     * @return array<string, mixed>
     */
    protected function props(string $path, string $component): array
    {
        $props = [];

        $this->get($this->tenantUrl($path))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($component, &$props) {
                $page->component($component);
                $props = $page->toArray()['props'];
                $page->etc();
            });

        return $props;
    }

    protected function siteName(string $name): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = $name;
        $settings->save();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staff(array $permissions): User
    {
        return User::forceCreate(['name' => 'Staff', 'email' => 'staff-'.Str::random(10).'@store.test', 'password' => 'password', 'email_verified_at' => now()])
            ->givePermissionTo($permissions);
    }
}
