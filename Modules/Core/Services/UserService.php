<?php

namespace Modules\Core\Services;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    private const FOREIGN_KEY_VIOLATION = '23503';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Only a Super Admin manages a Super Admin: `User Update` must not be a way to take over the store.
     */
    public function canManage(User $actor, User $target): bool
    {
        return ! $target->hasRole(UserRole::SUPER_ADMIN->value) || $actor->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        $roleNames = $this->roleNames($data['roles'], $actor);

        return DB::transaction(function () use ($data, $roleNames): User {
            /** @var User $user */
            $user = $this->users->create($this->attributes($data));
            $user->syncRoles($roleNames);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $roleNames = $this->roleNames($data['roles'], $actor);

        if (! in_array(UserRole::SUPER_ADMIN->value, $roleNames, true) && $this->isLastSuperAdmin($user)) {
            throw ValidationException::withMessages(['roles' => __('The store must keep at least one Super Admin.')]);
        }

        return DB::transaction(function () use ($user, $data, $roleNames): User {
            /** @var User $user */
            $user = $this->users->update($user, $this->attributes($data));
            $user->syncRoles($roleNames);

            return $user;
        });
    }

    /**
     * @throws DomainException when this user must stay
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new DomainException(__('You cannot delete your own account.'));
        }

        if ($this->isLastSuperAdmin($user)) {
            throw new DomainException(__('The store must keep at least one Super Admin.'));
        }

        if ($this->users->hasRecordsDeletedWithUser($user)) {
            throw new DomainException(__('This user created branches, orders or other records that would be deleted with them.'));
        }

        try {
            DB::transaction(fn () => $this->users->delete($user));
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::FOREIGN_KEY_VIOLATION) {
                throw $exception;
            }

            throw new DomainException(__('This user is linked to other records and cannot be deleted.'));
        }
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value)
            && $this->users->countWithRole(UserRole::SUPER_ADMIN->value) <= 1;
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function roleNames(array $ids, User $actor): array
    {
        $names = $this->roles->namesOf($ids);

        if (in_array(UserRole::SUPER_ADMIN->value, $names, true) && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
            throw ValidationException::withMessages(['roles' => __('Only a Super Admin can give the Super Admin role.')]);
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
        ];

        // The model's `hashed` cast hashes it. An empty password on edit keeps the current one.
        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        return $attributes;
    }
}
