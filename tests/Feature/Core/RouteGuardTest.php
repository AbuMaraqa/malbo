<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\EnsureStaff;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The admin panel is for signed-in staff only. Middleware is checked after resolving aliases and
 * groups, so a route counts only if the middleware really runs on it.
 */
class RouteGuardTest extends TestCase
{
    public function test_admin_routes_require_login_and_staff(): void
    {
        $admin = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => Str::is(['{locale}/admin', '{locale}/admin/*'], $route->uri()));

        $this->assertNotEmpty($admin);

        foreach ($admin as $route) {
            $middleware = $this->middlewareClasses($route);

            $this->assertContains(Authenticate::class, $middleware, "{$route->uri()} must require login.");
            $this->assertContains(EnsureStaff::class, $middleware, "{$route->uri()} must be staff only.");
        }

        $this->assertSame('{locale}/admin', $this->routeNamed('dashboard')->uri());
    }

    public function test_the_home_page_is_public(): void
    {
        $this->assertNotContains(Authenticate::class, $this->middlewareClasses($this->routeNamed('home')));
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
