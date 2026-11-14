<?php

namespace Modules\Store\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartItem;
use Modules\Store\Entities\CartReminder;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;

class CartRepository extends BaseEloquentRepository implements CartRepositoryInterface
{
    public function __construct(Cart $model)
    {
        parent::__construct($model);
    }

    public function findOpenByToken(string $token): ?Cart
    {
        /** @var Cart|null */
        return $this->query()->with('items')->where('token', $token)->whereNull('order_id')->first();
    }

    public function findByToken(string $token): ?Cart
    {
        /** @var Cart|null */
        return $this->query()->with('items')->where('token', $token)->first();
    }

    public function lock(int $id): ?Cart
    {
        /** @var Cart|null */
        return $this->query()->lockForUpdate()->with('items')->find($id);
    }

    public function countByToken(string $token): int
    {
        $carts = $this->model->getTable();

        return (int) (new CartItem)->newQuery()
            ->join($carts, "{$carts}.id", '=', (new CartItem)->qualifyColumn('cart_id'))
            ->where("{$carts}.token", $token)
            ->whereNull("{$carts}.order_id")
            // An account's cart counts for that account only (StorefrontLayout asks CartService::count).
            ->whereNull("{$carts}.user_id")
            ->sum((new CartItem)->qualifyColumn('quantity'));
    }

    public function findOpenForUser(int $userId): ?Cart
    {
        /** @var Cart|null */
        return $this->query()->with('items')->where('user_id', $userId)->whereNull('order_id')->latest('last_activity_at')->orderByDesc('id')->first();
    }

    public function findByUuid(string $uuid): ?Cart
    {
        /** @var Cart|null */
        return $this->query()->where('uuid', $uuid)->first();
    }

    public function dueForReminders(CarbonInterface $quietSince, CarbonInterface $activeAfter): LazyCollection
    {
        return $this->query()
            ->with(['items', 'user', 'reminders'])
            ->whereNull('order_id')
            ->whereNotNull('contact_consent_at')
            ->where('last_activity_at', '<=', $quietSince)
            ->where('last_activity_at', '>=', $activeAfter)
            ->whereHas('items')
            ->lazyById(100);
    }

    public function abandonedQuery(int $minutes): Builder
    {
        $reminders = (new CartReminder)->getTable();

        return $this->query()
            ->with(['user'])
            ->withCount(['items', 'reminders as reminders_sent' => fn (Builder $query) => $query->where($reminders.'.status', CartReminder::SENT)])
            ->withSum('items as pieces', 'quantity')
            ->whereNotNull('contact_consent_at')
            ->whereHas('items')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $open) => $open->whereNull('order_id')->where('last_activity_at', '<=', Carbon::now()->subMinutes($minutes)))
                ->orWhere(fn (Builder $ordered) => $ordered->whereNotNull('order_id')->whereHas('reminders', fn (Builder $sent) => $sent->where('status', CartReminder::SENT))));
    }

    public function findForAdmin(int $id): ?Cart
    {
        /** @var Cart|null */
        return $this->query()->with(['items', 'user', 'order', 'reminders'])->find($id);
    }
}
