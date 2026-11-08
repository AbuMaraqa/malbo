<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

interface RoleRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every role with its permissions and how many users have it.
     */
    public function tableQuery(): Builder;

    /**
     * Names of the roles with these ids.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    public function namesOf(array $ids): array;

    /**
     * Every role as a lookup, sorted by name.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function lookup(): Collection;

    /**
     * @return Collection<int, Permission>
     */
    public function allPermissions(): Collection;
}
