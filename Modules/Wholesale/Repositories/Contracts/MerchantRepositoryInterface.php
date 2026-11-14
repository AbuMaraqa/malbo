<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Wholesale\Entities\Merchant;

interface MerchantRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: merchants with their user, city, tier and sales rep.
     */
    public function tableQuery(): Builder;

    /**
     * A merchant with everything the admin's page shows, its status history included.
     */
    public function forEdit(int $id): Merchant;

    /**
     * The wholesale profile of this user, if any.
     */
    public function findForUser(int $userId): ?Merchant;

    /**
     * The merchant locked for the rest of the transaction.
     */
    public function lock(int $id): Merchant;

    /**
     * Staff who can be a merchant's sales rep: users with a role other than the buyers', or with a
     * permission of their own. By name.
     *
     * @return list<array{value: int, label: string}>
     */
    public function salesRepOptions(): array;

    /**
     * Whether this user can be a sales rep (see salesRepOptions).
     */
    public function isStaff(int $userId): bool;
}
