<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\Address;

interface AddressRepositoryInterface extends RepositoryInterface
{
    /**
     * The user's addresses, the default first, then the newest.
     *
     * @return Collection<int, Address>
     */
    public function forUser(int $userId): Collection;

    /**
     * One of the user's addresses; another user's is a 404.
     */
    public function findForUser(int $userId, int $id): Address;

    public function countForUser(int $userId): int;

    /**
     * The user's addresses locked for the rest of the transaction (the default moves between them).
     *
     * @return Collection<int, Address>
     */
    public function lockForUser(int $userId): Collection;
}
