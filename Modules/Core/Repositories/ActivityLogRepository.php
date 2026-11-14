<?php

namespace Modules\Core\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Entities\User;
use Modules\Core\Repositories\Contracts\ActivityLogRepositoryInterface;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function tableQuery(array $subjectTypes): Builder
    {
        return Activity::query()->with('causer')->whereIn('subject_type', $subjectTypes);
    }

    public function causers(array $subjectTypes): Collection
    {
        $ids = Activity::query()
            ->whereIn('subject_type', $subjectTypes)
            ->where('causer_type', (new User)->getMorphClass())
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        return User::query()->whereKey($ids)->orderBy('name')->get(['id', 'name'])->toBase();
    }
}
