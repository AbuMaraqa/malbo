<?php

namespace Modules\Core\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;

class UserRepository extends BaseEloquentRepository implements UserRepositoryInterface
{
    /**
     * Their `created_by` foreign key is cascadeOnDelete.
     *
     * @var list<class-string<Model>>
     */
    private const CASCADING = [Branch::class, Brand::class, Offer::class, Addon::class, Label::class, Order::class];

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['roles', 'branch.translations']);
    }

    public function findWithRoles(int $id): User
    {
        /** @var User */
        return $this->query()->with(['roles', 'branch.translations'])->findOrFail($id);
    }

    public function countWithRole(string $role): int
    {
        return $this->query()->whereHas('roles', fn (Builder $query) => $query->where('name', $role))->count();
    }

    public function hasRecordsDeletedWithUser(User $user): bool
    {
        foreach (self::CASCADING as $model) {
            if ((new $model)->newQueryWithoutScopes()->where('created_by', $user->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
