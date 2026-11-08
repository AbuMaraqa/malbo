<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Items\Entities\Order;
use Spatie\Permission\Models\Role;

/**
 * The numbers behind the dashboard. Every one is counted from the store's database.
 */
interface DashboardRepositoryInterface
{
    public function usersCount(): int;

    /**
     * Users with the role Customer.
     */
    public function clientsCount(): int;

    public function productsCount(): int;

    public function ordersCount(): int;

    /**
     * Orders per month since the given date.
     *
     * @return Collection<string, int> count by `YYYY-MM`; months without orders are absent
     */
    public function ordersByMonth(Carbon $from): Collection;

    /**
     * Users registered per month since the given date.
     *
     * @return Collection<string, int> count by `YYYY-MM`; months without users are absent
     */
    public function usersByMonth(Carbon $from): Collection;

    /**
     * @return Collection<int, int> count by order status value
     */
    public function ordersByStatus(): Collection;

    /**
     * Every role with `users_count`, sorted by name.
     *
     * @return Collection<int, Role>
     */
    public function rolesWithUsersCount(): Collection;

    /**
     * The latest orders with their customer.
     *
     * @return Collection<int, Order>
     */
    public function recentOrders(int $limit): Collection;
}
