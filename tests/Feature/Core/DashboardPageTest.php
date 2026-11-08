<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;

class DashboardPageTest extends DatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->admin = $this->makeUser('admin@example.com')->assignRole('Super Admin');
    }

    public function test_the_metrics_are_the_real_counts(): void
    {
        $this->makeUser('client@example.com')->assignRole('Customer');
        $this->makeOrder('A-1');

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Home/Index')
                ->where('metrics', fn ($metrics) => collect($metrics)->pluck('value', 'key')->all() === [
                    'users' => User::query()->count(),
                    'clients' => User::role('Customer')->count(),
                    'products' => \Modules\Items\Entities\Product::query()->count(),
                    'orders' => 1,
                ]));
    }

    public function test_the_metrics_carry_no_invented_trend(): void
    {
        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics', fn ($metrics) => collect($metrics)->every(fn ($metric) => array_keys((array) $metric) === ['key', 'title', 'value'])));
    }

    public function test_orders_per_month_counts_each_month_and_leaves_out_older_and_deleted_orders(): void
    {
        Carbon::setTestNow('2026-11-20 12:00:00');

        $this->makeOrder('NOW-1', createdAt: now());
        $this->makeOrder('NOW-2', createdAt: now()->subDay());
        $this->makeOrder('JAN', createdAt: Carbon::parse('2026-01-31 10:00:00'));
        $this->makeOrder('OLD', createdAt: Carbon::parse('2025-11-30 23:00:00'));
        $this->makeOrder('DELETED', createdAt: now())->delete();

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ordersPerMonth.categories', 12)
                ->where('ordersPerMonth.data', [0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2]));

        Carbon::setTestNow();
    }

    public function test_users_by_role_counts_users_of_each_role(): void
    {
        $this->makeUser('client@example.com')->assignRole('Customer');

        $role = Role::findByName('Customer', 'web');

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(function (Assert $page) use ($role) {
                $chart = $page->toArray()['props']['usersByRole'];
                $index = array_search(__('Customer'), $chart['labels'], true);

                $this->assertNotFalse($index);
                $this->assertSame($role->users()->count(), $chart['data'][$index]);
            });
    }

    public function test_orders_by_status_and_recent_orders_show_real_orders(): void
    {
        $customer = $this->makeUser('client@example.com', 'Client Name');

        $this->makeOrder('R-1', status: OrderStatus::DELIVERED, customer: $customer);
        $this->makeOrder('R-2', status: OrderStatus::DELIVERED);
        $this->makeOrder('R-3', status: OrderStatus::CANCELLED);

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ordersByStatus.labels', [OrderStatus::DELIVERED->getLabel(), OrderStatus::CANCELLED->getLabel()])
                ->where('ordersByStatus.data', [2, 1])
                ->where('recentOrders.rows.0.order_number', 'R-3')
                ->where('recentOrders.rows.0.tone', 'danger')
                ->where('recentOrders.rows.2.order_number', 'R-1')
                ->where('recentOrders.rows.2.customer', 'Client Name')
                ->where('recentOrders.rows.2.total', '10.00'));
    }

    public function test_recent_orders_keeps_only_the_last_five(): void
    {
        foreach (range(1, 7) as $number) {
            $this->makeOrder('N-'.$number);
        }

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page->has('recentOrders.rows', 5)->where('recentOrders.rows.0.order_number', 'N-7'));
    }

    public function test_without_orders_the_widgets_are_empty_not_invented(): void
    {
        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ordersPerMonth.data', array_fill(0, 12, 0))
                ->where('ordersByStatus.data', [])
                ->where('recentOrders.rows', []));
    }

    public function test_each_widget_follows_its_permission(): void
    {
        $onlyProducts = $this->makeUser('products@example.com')->givePermissionTo('Product View List');

        $this->actingAs($onlyProducts)
            ->get($this->siteUrl('/ar/admin'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics', fn ($metrics) => collect($metrics)->pluck('key')->all() === ['products'])
                ->where('quickLinks', fn ($links) => collect($links)->pluck('key')->all() === ['products'])
                ->where('ordersPerMonth', null)
                ->where('ordersByStatus', null)
                ->where('recentOrders', null)
                ->where('usersByRole', null)
                ->where('newUsersPerMonth', null));

        $onlyOrders = $this->makeUser('orders@example.com')->givePermissionTo('Order View List');

        $this->actingAs($onlyOrders)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics', fn ($metrics) => collect($metrics)->pluck('key')->all() === ['orders'])
                ->has('ordersPerMonth.data', 12)
                ->where('usersByRole', null));

        $onlyUsers = $this->makeUser('users@example.com')->givePermissionTo('User View List');

        $this->actingAs($onlyUsers)
            ->get($this->siteUrl('/ar/admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics', fn ($metrics) => collect($metrics)->pluck('key')->all() === ['users', 'clients'])
                ->has('usersByRole.labels')
                ->has('newUsersPerMonth.data', 12)
                ->where('recentOrders', null));
    }

    public function test_a_staff_member_with_no_dashboard_permission_still_opens_the_page(): void
    {
        $user = $this->makeUser('none@example.com')->givePermissionTo('Currency View List');

        $this->actingAs($user)
            ->get($this->siteUrl('/ar/admin'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metrics', [])->where('quickLinks', []));
    }

    public function test_a_customer_is_sent_away_and_a_guest_to_login(): void
    {
        $this->get($this->siteUrl('/ar/admin'))->assertRedirect();

        $this->actingAs($this->makeUser('c@example.com')->assignRole('Customer'))
            ->get($this->siteUrl('/ar/admin'))
            ->assertRedirect();
    }

    private function makeUser(string $email, string $name = 'Someone'): User
    {
        return User::forceCreate(['name' => $name, 'email' => $email, 'password' => 'secret-password']);
    }

    private function makeOrder(string $number, ?Carbon $createdAt = null, OrderStatus $status = OrderStatus::PENDING, ?User $customer = null): Order
    {
        $order = new Order;
        $order->forceFill([
            'order_number' => $number,
            'user_id' => $customer?->id,
            'sub_total' => 10,
            'total' => 10,
            'status' => $status,
            'delivery_address' => 'Address',
            'contact_phone' => '0599',
            'created_by' => $this->admin->id,
        ]);

        if ($createdAt) {
            $order->created_at = $createdAt;
        }

        $order->save();

        return $order;
    }
}
