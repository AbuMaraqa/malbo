<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\Member;
use Modules\Store\Repositories\Contracts\MemberRepositoryInterface;

class MemberRepository extends BaseEloquentRepository implements MemberRepositoryInterface
{
    public function __construct(Member $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->latest('id');
    }

    public function findByPhone(string $phone): ?Member
    {
        return $this->query()->where('phone', $phone)->first();
    }
}
