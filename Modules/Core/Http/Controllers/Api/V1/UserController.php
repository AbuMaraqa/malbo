<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Modules\Core\Http\Requests\UserRequestUpdate;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Services\UserService;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserService $userService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="Get all users",
     *     description="Retrieve a list of all users",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         required=false,
     *         description="Comma separated list of fields to be returned",
     *         @OA\Schema(type="string", example="id,name,email")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Anas"),
     *                     @OA\Property(property="email", type="string", example="anas@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = max(1, min((int) request('per_page', $defaultPerPage), $maxPerPage));

        $users = QueryBuilder::for($this->userRepository->query())
            ->with('media')
            ->allowedFields(...UserResource::allowedFields())
            ->allowedSorts(...UserResource::allowedSorts())
            ->allowedFilters(...UserResource::allowedFilters())
            ->allowedIncludes(...UserResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            UserResource::collection($users),
            __('Users retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{user}",
     *     summary="Get a single user",
     *     description="Retrieve details of a specific user by ID",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *          name="fields",
     *          in="path",
     *          required=false,
     *          description="Comma separated list of fields to be returned",
     *          @OA\Schema(type="string", example="id,name,email")
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Anas"),
     *                 @OA\Property(property="email", type="string", example="anas@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(Request $request, User $user)
    {
        // A buyer reads their own record; everybody else's needs the permission of the users page.
        abort_unless($request->user()->is($user) || $request->user()->can('User View List'), 403);

        $user = QueryBuilder::for($this->userRepository->query())
            ->with('media')
            ->allowedFields(...UserResource::allowedFields())
            ->allowedSorts(...UserResource::allowedSorts())
            ->allowedIncludes(...UserResource::allowedIncludes())
            ->findOrFail($user->id);

        return $this->successResponse(
            new UserResource($user),
            __('User retrieved successfully')
        );
    }
    /**
     * Store a newly created resource in storage.
     */
/**
     * @OA\Put(
     *     path="/api/v1/users/{user}",
     *     summary="Update user",
     *     description="Update the details of a specific user by ID",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email"},
     *             @OA\Property(property="name", type="string", example="Anas"),
     *             @OA\Property(property="email", type="string", format="email", example="anas@example.com"),
     *             @OA\Property(property="phone", type="string", example="+970599123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Anas"),
     *                 @OA\Property(property="email", type="string", example="anas@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+970599123456")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function update(UserRequestUpdate $request, User $user)
    {
        $actor = $request->user();

        // Your own profile, or anybody's with the permission of the users page, except that only a
        // Super Admin edits a Super Admin. Roles and branch are never changed here.
        abort_unless($actor->is($user) || ($actor->can('User Update') && $this->userService->canManage($actor, $user)), 403);

        $this->userRepository->update($user, $request->safe()->only(['name', 'email', 'phone', 'password']));

        if ($request->hasFile('cover_photo')) {
            $user->addMediaFromRequest('cover_photo')
                ->toMediaCollection('cover_photo', 'media');
        }

        if ($request->hasFile('avatar')) {
            $user->addMediaFromRequest('avatar')
                ->toMediaCollection('avatar', 'media');
        }

        return $this->successResponse(
            new UserResource($user->fresh()),
            __('User updated successfully')
        );
    }
    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/v1/users/{user}",
     *     summary="Delete user",
     *     description="Delete a specific user by ID",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deleted successfully"),
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy(Request $request, User $user)
    {
        abort_unless($this->userService->canManage($request->user(), $user), 403);

        try {
            $this->userService->delete($user, $request->user());
        } catch (DomainException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->successResponse(
            null,
            __('User deleted successfully')
        );
    }
}
