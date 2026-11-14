<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Entities\User;

/**
 * The activity log (spatie/laravel-activitylog), read only: the models write it themselves.
 */
interface ActivityLogRepositoryInterface
{
    /**
     * The admin table's query, with who caused each entry: only entries about these kinds of
     * record (class names), the ones the viewer may see.
     *
     * @param  list<string>  $subjectTypes
     */
    public function tableQuery(array $subjectTypes): Builder;

    /**
     * The users who caused at least one entry about these kinds of record, by name.
     *
     * @param  list<string>  $subjectTypes
     * @return Collection<int, User>
     */
    public function causers(array $subjectTypes): Collection;
}
