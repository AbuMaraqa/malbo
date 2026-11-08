<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every user with roles and branch.
     */
    public function tableQuery(): Builder;

    public function findWithRoles(int $id): User;

    public function countWithRole(string $role): int;

    /**
     * Records whose foreign key deletes them with the user who created them.
     */
    public function hasRecordsDeletedWithUser(User $user): bool;
}
