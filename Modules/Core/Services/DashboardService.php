<?php

namespace Modules\Core\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Modules\Core\Entities\User;
use Modules\Core\Repositories\Contracts\DashboardRepositoryInterface;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Spatie\Permission\Models\Role;

/**
 * What the signed-in staff member sees on the dashboard. A widget the user may not see is null
 * and its numbers are not even counted, so they never reach the browser.
 */
class DashboardService
{
    private const MONTHS = 12;

    private const RECENT_ORDERS = 5;

    /** The quick links of core's HomeActionsWidget. */
    private const QUICK_LINKS = [
        ['key' => 'branches', 'label' => 'Branches', 'route' => 'branches.index', 'permission' => 'Branch View List'],
        ['key' => 'products', 'label' => 'Products', 'route' => 'products.index', 'permission' => 'Product View List'],
        ['key' => 'categories', 'label' => 'Categories', 'route' => 'categories.index', 'permission' => 'Category View List'],
    ];

    public function __construct(private readonly DashboardRepositoryInterface $dashboard) {}

    /**
     * @return array<string, mixed>
     */
    public function widgets(User $user): array
    {
        $canUsers = $user->can('User View List');
        $canOrders = $user->can('Order View List');

        return [
            'metrics' => $this->metrics($user, $canUsers, $canOrders),
            'quickLinks' => $this->quickLinks($user),
            'ordersPerMonth' => $canOrders ? $this->perMonth($this->dashboard->ordersByMonth($this->from())) : null,
            'usersByRole' => $canUsers ? $this->usersByRole() : null,
            'newUsersPerMonth' => $canUsers ? $this->perMonth($this->dashboard->usersByMonth($this->from())) : null,
            'ordersByStatus' => $canOrders ? $this->ordersByStatus() : null,
            'recentOrders' => $canOrders ? $this->recentOrders($user) : null,
        ];
    }

    /**
     * @return list<array{key: string, title: string, value: int}>
     */
    private function metrics(User $user, bool $canUsers, bool $canOrders): array
    {
        $metrics = [];

        if ($canUsers) {
            $metrics[] = ['key' => 'users', 'title' => __('Total Users'), 'value' => $this->dashboard->usersCount()];
            $metrics[] = ['key' => 'clients', 'title' => __('Total Clients'), 'value' => $this->dashboard->clientsCount()];
        }

        if ($user->can('Product View List')) {
            $metrics[] = ['key' => 'products', 'title' => __('Total Products'), 'value' => $this->dashboard->productsCount()];
        }

        if ($canOrders) {
            $metrics[] = ['key' => 'orders', 'title' => __('Total Orders'), 'value' => $this->dashboard->ordersCount()];
        }

        return $metrics;
    }

    /**
     * @return list<array{key: string, label: string, url: string|null}>
     */
    private function quickLinks(User $user): array
    {
        return collect(self::QUICK_LINKS)
            ->filter(fn (array $link) => $user->can($link['permission']))
            ->map(fn (array $link) => [
                'key' => $link['key'],
                'label' => __($link['label']),
                'url' => Route::has($link['route']) ? route($link['route']) : null,
            ])
            ->values()
            ->all();
    }

    private function from(): Carbon
    {
        return now()->startOfMonth()->subMonthsNoOverflow(self::MONTHS - 1);
    }

    /**
     * The last twelve months, months without rows as zero.
     *
     * @param  Collection<string, int>  $counts
     * @return array{categories: list<string>, data: list<int>}
     */
    private function perMonth(Collection $counts): array
    {
        $categories = [];
        $data = [];

        for ($month = $this->from(), $i = 0; $i < self::MONTHS; $i++, $month = $month->copy()->addMonthNoOverflow()) {
            $categories[] = $month->copy()->locale(app()->getLocale())->translatedFormat('M Y');
            $data[] = $counts->get($month->format('Y-m'), 0);
        }

        return ['categories' => $categories, 'data' => $data];
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    private function usersByRole(): array
    {
        $roles = $this->dashboard->rolesWithUsersCount();

        return [
            'labels' => $roles->map(fn (Role $role) => __($role->name))->values()->all(),
            'data' => $roles->map(fn (Role $role) => (int) $role->users_count)->values()->all(),
        ];
    }

    /**
     * Statuses that have orders, in the order of the workflow.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    private function ordersByStatus(): array
    {
        $counts = $this->dashboard->ordersByStatus();
        $statuses = array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $counts->has($status->value)));

        return [
            'labels' => array_map(fn (OrderStatus $status) => $status->getLabel(), $statuses),
            'data' => array_map(fn (OrderStatus $status) => $counts->get($status->value), $statuses),
        ];
    }

    /**
     * @return array{url: string|null, rows: list<array<string, mixed>>}
     */
    private function recentOrders(User $user): array
    {
        $canEdit = $user->can('Order Update') && Route::has('orders.edit');

        return [
            'url' => Route::has('orders.index') ? route('orders.index') : null,
            'rows' => $this->dashboard->recentOrders(self::RECENT_ORDERS)
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user?->name,
                    'total' => number_format((float) $order->total, 2),
                    'status' => $order->status?->getLabel(),
                    'tone' => $this->tone($order->status),
                    'date' => $order->created_at?->format('Y-m-d H:i'),
                    'url' => $canEdit ? route('orders.edit', $order->id) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * OrderStatus::getColor() names a Filament color; the Badge component has its own tones.
     */
    private function tone(?OrderStatus $status): string
    {
        return match ($status?->getColor()) {
            'warning' => 'warning',
            'info' => 'info',
            'primary' => 'brand',
            'success' => 'success',
            'danger' => 'danger',
            default => 'neutral',
        };
    }
}
