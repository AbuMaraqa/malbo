<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Entities\User;
use Modules\Core\Repositories\Contracts\ActivityLogRepositoryInterface;
use Modules\Core\Support\ActivityLogRows;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The global activity log, read only: who changed which record, when, and what changed. A viewer
 * sees only entries about records they may list, and secrets never reach the page (ActivityLogRows).
 */
class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activities) {}

    public function index(Request $request): Response
    {
        // Only entries about records the viewer may list (an order's entries need Order View List...).
        $types = ActivityLogRows::visibleTypes($request->user());

        return Inertia::render('Core/ActivityLog/Index', [
            'table' => AdminTable::for($this->activities->tableQuery($types))
                ->filters(
                    AdminFilter::exact('subject_type'),
                    AdminFilter::exact('event'),
                    AllowedFilter::callback('causer_id', function (Builder $query, mixed $value): void {
                        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);

                        if ($id !== false) {
                            $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id);
                        }
                    }),
                    AllowedFilter::callback('subject_id', function (Builder $query, mixed $value): void {
                        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);

                        if ($id !== false) {
                            $query->where('subject_id', $id);
                        }
                    }),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('id', 'created_at')
                ->rows(fn (Activity $activity) => ActivityLogRows::row($activity)),
            'subjectTypes' => ActivityLogRows::subjectOptions($request->user()),
            'events' => ActivityLogRows::eventOptions(),
            'causers' => $this->activities->causers($types)->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])->values(),
        ]);
    }
}
