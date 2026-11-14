<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Reports\database\seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;

/**
 * The Reports module: its permissions, its sidebar group, its routes and its indexes.
 */
class ReportsModuleTest extends DatabaseTestCase
{
    private const ROUTES = ['sales', 'channels', 'products', 'sellers', 'returns', 'merchants', 'carts', 'conversion'];

    public function test_permissions_are_seeded_and_given_to_super_admin(): void
    {
        $admin = Role::findByName('Super Admin');

        foreach (PermissionSeeder::PERMISSIONS as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission), $permission);
        }

        $this->assertCount(8, PermissionSeeder::PERMISSIONS);
    }

    public function test_the_sidebar_group(): void
    {
        $this->actingAs(User::query()->whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))->firstOrFail());

        $group = collect(app(SidebarService::class)->all())->firstWhere('title', 'Reports');

        $this->assertNotNull($group);
        $this->assertSame('Sales', $group['section']);
        $this->assertSame('reports', $group['icon']);
        $this->assertCount(8, $group['children']);
        $this->assertSame('reports.sales.index', $group['children'][0]['route']);
    }

    public function test_every_report_route_needs_its_permission(): void
    {
        $permissions = array_combine(self::ROUTES, PermissionSeeder::PERMISSIONS);

        foreach ($permissions as $name => $permission) {
            foreach (['index', 'export'] as $action) {
                $route = Route::getRoutes()->getByName("reports.{$name}.{$action}");

                $this->assertNotNull($route, "reports.{$name}.{$action}");
                $this->assertSame(['GET', 'HEAD'], $route->methods());
                $this->assertContains("can:{$permission}", $route->gatherMiddleware(), "reports.{$name}.{$action}");
                $this->assertContains('staff', $route->gatherMiddleware());
                $this->assertStringStartsWith('{locale}/admin/reports/'.$name, $route->uri());
            }
        }
    }

    public function test_the_indexes_exist(): void
    {
        $indexes = DB::table('pg_indexes')->whereLike('indexname', '%_report_%')->pluck('indexname')->sort()->values()->all();

        $this->assertSame([
            'items_orders_report_created_idx',
            'items_return_requests_report_status_type_idx',
            'store_cart_reminders_report_sent_idx',
            'store_carts_report_created_idx',
            'wholesale_carts_report_created_idx',
        ], $indexes);
    }
}
