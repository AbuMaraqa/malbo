<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;

class MerchantRepository extends BaseEloquentRepository implements MerchantRepositoryInterface
{
    public function __construct(Merchant $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['user', 'city.translations', 'tier.translations', 'salesRep']);
    }

    public function forEdit(int $id): Merchant
    {
        /** @var Merchant */
        return $this->query()
            ->with(['user', 'city.translations', 'tier.translations', 'salesRep', 'statusChanges.createdBy'])
            ->findOrFail($id);
    }

    public function findForUser(int $userId): ?Merchant
    {
        /** @var Merchant|null */
        return $this->query()->where('user_id', $userId)->first();
    }

    public function lock(int $id): Merchant
    {
        /** @var Merchant */
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function salesRepOptions(): array
    {
        return $this->staffQuery()->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (int) $user->id, 'label' => (string) $user->name])
            ->values()
            ->all();
    }

    public function isStaff(int $userId): bool
    {
        return $this->staffQuery()->whereKey($userId)->exists();
    }

    /**
     * As EnsureStaff decides it.
     */
    private function staffQuery(): Builder
    {
        return User::query()->where(fn (Builder $query) => $query
            ->whereHas('roles', fn (Builder $roles) => $roles->whereNotIn('name', UserRole::nonStaff()))
            ->orWhereHas('permissions'));
    }
}
