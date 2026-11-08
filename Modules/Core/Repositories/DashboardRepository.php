<?php

namespace Modules\Core\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\DashboardRepositoryInterface;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Spatie\Permission\Models\Role;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function usersCount(): int
    {
        return User::query()->count();
    }

    public function clientsCount(): int
    {
        return User::query()->whereHas('roles', fn (Builder $query) => $query->where('name', UserRole::CUSTOMER->value))->count();
    }

    public function productsCount(): int
    {
        return Product::query()->count();
    }

    public function ordersCount(): int
    {
        return Order::query()->count();
    }

    public function ordersByMonth(Carbon $from): Collection
    {
        return $this->countByMonth(Order::query(), $from);
    }

    public function usersByMonth(Carbon $from): Collection
    {
        return $this->countByMonth(User::query(), $from);
    }

    public function ordersByStatus(): Collection
    {
        return Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
    }

    public function rolesWithUsersCount(): Collection
    {
        return Role::query()->withCount('users')->orderBy('name')->get()->toBase();
    }

    public function recentOrders(int $limit): Collection
    {
        return Order::query()->with('user:id,name')->latest('id')->limit($limit)->get()->toBase();
    }

    /**
     * @return Collection<string, int>
     */
    private function countByMonth(Builder $query, Carbon $from): Collection
    {
        return $query
            ->where('created_at', '>=', $from)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->toBase()
            ->pluck('total', 'month')
            ->map(fn ($total) => (int) $total);
    }
}
