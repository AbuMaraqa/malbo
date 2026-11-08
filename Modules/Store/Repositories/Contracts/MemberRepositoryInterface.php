<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\Member;

interface MemberRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin members table's query: newest first.
     */
    public function tableQuery(): Builder;

    /**
     * @param  string  $phone  digits only
     */
    public function findByPhone(string $phone): ?Member;
}
