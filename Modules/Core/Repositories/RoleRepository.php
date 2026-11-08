<?php

namespace Modules\Core\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository extends BaseEloquentRepository implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('permissions')->withCount('users');
    }

    public function namesOf(array $ids): array
    {
        return $this->query()->whereKey($ids)->pluck('name')->all();
    }

    public function lookup(): Collection
    {
        return $this->query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])
            ->toBase();
    }

    public function allPermissions(): Collection
    {
        return Permission::query()->get()->toBase();
    }
}
