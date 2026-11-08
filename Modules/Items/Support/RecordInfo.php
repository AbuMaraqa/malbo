<?php

namespace Modules\Items\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * What the Info action of a catalog table shows: who created the record, when, and its latest
 * 50 activities. Sent as explicit fields, so a user or a model never leaks a column.
 */
final class RecordInfo
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Model $record): array
    {
        $record->loadMissing('createdBy');

        return [
            'created_by' => $record->getRelation('createdBy')?->name,
            'created_at' => $record->getAttribute('created_at')?->format('Y-m-d H:i'),
            'updated_at' => $record->getAttribute('updated_at')?->format('Y-m-d H:i'),
            'activities' => $record->activitiesAsSubject()
                ->with('causer')
                ->latest()
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => __($activity->description),
                    'causer' => $activity->causer?->getAttribute('name'),
                    'old' => $activity->attribute_changes?->get('old'),
                    'new' => $activity->attribute_changes?->get('attributes'),
                    'created_at' => $activity->created_at?->format('Y-m-d H:i'),
                    'created_ago' => $activity->created_at?->diffForHumans(),
                ])
                ->all(),
        ];
    }
}
