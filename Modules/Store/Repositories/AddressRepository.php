<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\Address;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;

class AddressRepository extends BaseEloquentRepository implements AddressRepositoryInterface
{
    public function __construct(Address $model)
    {
        parent::__construct($model);
    }

    public function forUser(int $userId): Collection
    {
        return $this->query()
            ->with(['city.translations', 'district.translations'])
            ->where('user_id', $userId)
            ->orderByDesc('is_default')->orderByDesc('id')
            ->get();
    }

    public function findForUser(int $userId, int $id): Address
    {
        /** @var Address */
        return $this->query()->where('user_id', $userId)->findOrFail($id);
    }

    public function countForUser(int $userId): int
    {
        return $this->query()->where('user_id', $userId)->count();
    }

    public function lockForUser(int $userId): Collection
    {
        return $this->query()->where('user_id', $userId)->orderBy('id')->lockForUpdate()->get();
    }
}
