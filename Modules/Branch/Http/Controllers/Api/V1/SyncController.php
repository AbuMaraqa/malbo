<?php

namespace Modules\Branch\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Branch\Transformers\V1\BranchResource;


class SyncController extends Controller
{
    public function syncBranches(Request $request, BranchRepositoryInterface $branches)
    {
        $since = $request->validate(['since' => ['nullable', 'date']])['since'] ?? '1970-01-01 00:00:00';

        $changed = $branches->changedSince($since);

        return BranchResource::collection($changed)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }
}
