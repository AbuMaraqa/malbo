<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\InitializeTenancyIfTenantHost;
use App\Http\Middleware\ScopeSessionToStore;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Every registered route must be a store route (tenant group), a universal route (tenancy
 * initialized on store hosts), bound to a central domain, or a static asset. Middleware is checked
 * after resolving aliases and groups, so a route counts as a store route only if the tenancy
 * middleware really runs on it.
 */
class RouteGuardTest extends TestCase
{
    /**
     * Routes that serve files or code shipped with the app and touch no store data.
     */
    private const ASSET_URIS = [
        'up',
        'sanctum/csrf-cookie',
        'storage/*', // Laravel's signed `local` disk URLs; nothing generates them (no temporaryUrl callers)
    ];

    public function test_no_route_bypasses_tenancy(): void
    {
        $unclassified = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isTenantRoute($route) && ! $this->isUniversalRoute($route)
                && ! in_array($route->getDomain(), config('tenancy.central_domains'), true)
                && ! Str::is(self::ASSET_URIS, $route->uri())) {
                $unclassified[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $unclassified, 'Routes that are not tenant-aware.');
    }

    public function test_the_home_page_is_universal(): void
    {
        $this->assertTrue($this->isUniversalRoute($this->routeNamed('home')));
    }

    public function test_platform_routes_are_bound_to_the_central_domains(): void
    {
        $platform = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => Str::startsWith((string) $route->getName(), 'central.'));

        $this->assertNotEmpty($platform);

        foreach ($platform as $route) {
            $this->assertContains($route->getDomain(), config('tenancy.central_domains'), "{$route->uri()} must be on a central domain.");
        }
    }

    public function test_platform_admin_routes_require_the_platform_guard(): void
    {
        foreach (['dashboard', 'stores.status', 'stores.open-admin', 'stores.destroy', 'logout'] as $name) {
            $this->assertContains('auth:platform', $this->routeNamed("central.platform.{$name}")->gatherMiddleware(), "{$name} must require a platform admin.");
        }
    }

    public function test_admin_routes_require_login_and_staff(): void
    {
        $admin = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => Str::is(['{locale}/admin', '{locale}/admin/*'], $route->uri()));

        $this->assertNotEmpty($admin);

        foreach ($admin as $route) {
            $middleware = $this->middlewareClasses($route);

            $this->assertTrue($this->isTenantRoute($route), "{$route->uri()} must be a store route.");
            $this->assertContains(PreventAccessFromCentralDomains::class, $middleware, "{$route->uri()} must be in the tenant group.");
            $this->assertContains(Authenticate::class, $middleware, "{$route->uri()} must require login.");
            $this->assertContains(EnsureStaff::class, $middleware, "{$route->uri()} must be staff only.");
        }

        $this->assertSame('{locale}/admin', $this->routeNamed('dashboard')->uri());
    }

    public function test_sessions_are_scoped_before_auth(): void
    {
        $middleware = $this->middlewareClasses($this->routeNamed('dashboard'));

        $this->assertContains(ScopeSessionToStore::class, $middleware);
        $this->assertLessThan(
            array_search(Authenticate::class, $middleware, true),
            array_search(ScopeSessionToStore::class, $middleware, true),
            'Sessions must be scoped before auth reads the login.'
        );

        // Every route with a session scopes it.
        foreach (Route::getRoutes() as $route) {
            $classes = $this->middlewareClasses($route);

            if (in_array(StartSession::class, $classes, true)) {
                $this->assertContains(ScopeSessionToStore::class, $classes, "{$route->uri()} has a session that is not scoped.");
            }
        }
    }

    /**
     * Identifies the store by domain, so it cannot run without one: a central host is no store's
     * domain. (The `tenant` group adds PreventAccessFromCentralDomains for a plain 404 there.)
     */
    private function isTenantRoute(RoutingRoute $route): bool
    {
        return in_array(InitializeTenancyByDomain::class, $this->middlewareClasses($route), true);
    }

    private function isUniversalRoute(RoutingRoute $route): bool
    {
        return in_array(InitializeTenancyIfTenantHost::class, $this->middlewareClasses($route), true);
    }

    /**
     * @return list<string> the route's middleware with aliases and groups resolved, parameters stripped
     */
    private function middlewareClasses(RoutingRoute $route): array
    {
        return collect(app('router')->gatherRouteMiddleware($route))
            ->filter(fn ($middleware) => is_string($middleware))
            ->map(fn (string $middleware) => Str::before($middleware, ':'))
            ->values()
            ->all();
    }

    private function routeNamed(string $name): RoutingRoute
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route [{$name}] is not registered.");

        return $route;
    }
}
