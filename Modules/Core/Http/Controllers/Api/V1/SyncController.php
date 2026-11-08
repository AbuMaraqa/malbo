<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Transformers\V1\UserResource;

class SyncController extends Controller
{
    public function syncUsers(Request $request, UserRepositoryInterface $userRepository)
    {
        $since = $this->since($request);

        $users = $userRepository->query()->with('media', 'roles')->where('updated_at' , '>' , $since)->get();

        return UserResource::collection($users)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncRoles(Request $request, RoleRepositoryInterface $roleRepository)
    {
        $since = $this->since($request);

        $roles = $roleRepository->query()->with('permissions')
            ->where('updated_at', '>', $since)
            ->get();

        $data = $roles->map(function($role) {
            return [
                'id'          => $role->id,
                'name'        => $role->name,       // اسم الدور (مثلاً: kitchen)
                'permissions' => $role->permissions->pluck('name'), // مصفوفة الصلاحيات: ['view_orders', 'finish_cooking']
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * PostgreSQL rejects a malformed date with an error, not an empty result.
     */
    private function since(Request $request): string
    {
        return $request->validate(['since' => ['nullable', 'date']])['since'] ?? '1970-01-01 00:00:00';
    }
}
