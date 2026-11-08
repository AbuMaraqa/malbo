<?php

namespace Modules\Core\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    /**
     * The code looks these up by name: Customer decides who is staff, Super Admin who holds everything.
     */
    public function isProtected(Role $role): bool
    {
        return UserRole::tryFrom($role->name) !== null;
    }

    public function isSuperAdmin(Role $role): bool
    {
        return $role->name === UserRole::SUPER_ADMIN->value;
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function create(string $name, array $permissionIds): Role
    {
        return DB::transaction(function () use ($name, $permissionIds): Role {
            /** @var Role $role */
            $role = $this->roles->create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissionIds);

            return $role;
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function update(Role $role, string $name, array $permissionIds): Role
    {
        if ($this->isSuperAdmin($role)) {
            throw ValidationException::withMessages(['permissions' => __('The Super Admin role always has every permission.')]);
        }

        if ($this->isProtected($role) && $role->name !== $name) {
            throw ValidationException::withMessages(['name' => __('This role is used by the system and cannot be renamed.')]);
        }

        return DB::transaction(function () use ($role, $name, $permissionIds): Role {
            /** @var Role $role */
            $role = $this->roles->update($role, ['name' => $name]);
            $role->syncPermissions($permissionIds);

            return $role;
        });
    }

    /**
     * @throws DomainException when the role must stay
     */
    public function delete(Role $role): void
    {
        if ($this->isProtected($role)) {
            throw new DomainException(__('This role is used by the system and cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            throw new DomainException(__('This role is given to users. Move them to another role first.'));
        }

        DB::transaction(fn () => $this->roles->delete($role));
    }
}
