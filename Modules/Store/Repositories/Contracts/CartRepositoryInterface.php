<?php

namespace Modules\Store\Repositories\Contracts;

use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Modules\Store\Entities\Cart;

interface CartRepositoryInterface extends RepositoryInterface
{
    /**
     * The open (not yet ordered) cart with this cookie token, with its items.
     */
    public function findOpenByToken(string $token): ?Cart;

    /**
     * The cart with this token, open or converted (a second checkout submit finds its order).
     */
    public function findByToken(string $token): ?Cart;

    /**
     * The cart locked for the rest of the transaction, with its items.
     */
    public function lock(int $id): ?Cart;

    /**
     * How many pieces the open cart with this token holds.
     */
    public function countByToken(string $token): int;

    /**
     * The signed-in user's open cart (the newest, if there are several), with its items.
     */
    public function findOpenForUser(int $userId): ?Cart;

    /**
     * The cart with this public id (a reminder's restore link), open or not.
     */
    public function findByUuid(string $uuid): ?Cart;

    /**
     * Open carts with items whose buyer agreed to reminders, quiet since $quietSince but active after
     * $activeAfter, with their items, account and reminders. Read lazily, in id order.
     *
     * @return LazyCollection<int, Cart>
     */
    public function dueForReminders(CarbonInterface $quietSince, CarbonInterface $activeAfter): LazyCollection;

    /**
     * The admin's abandoned carts: with items and an agreed contact; open ones quiet for $minutes or
     * more, and ordered ones that had a reminder (recovered). With the account, the item and
     * reminder counts.
     */
    public function abandonedQuery(int $minutes): Builder;

    /**
     * One cart for the admin's detail page, with its items, account, order and reminders.
     */
    public function findForAdmin(int $id): ?Cart;
}
