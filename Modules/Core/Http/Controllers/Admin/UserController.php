<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Http\Requests\Admin\UserRequest;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Services\UserService;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly BranchRepositoryInterface $branches,
        private readonly UserService $userService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Core/Users/Index', [
            'table' => AdminTable::for($this->users->tableQuery())
                ->search(['name', 'email', 'phone'], null)
                ->filters(
                    AdminFilter::relation('role', 'roles'),
                    AdminFilter::relation('branch', 'branch'),
                    AdminFilter::text('name'),
                    AdminFilter::text('email'),
                    AdminFilter::text('phone'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('name', 'email', 'created_at', 'updated_at', 'id')
                ->rows(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->roles->map(fn (Role $role) => [
                        'name' => $role->name,
                        'label' => __($role->name),
                    ])->values(),
                    'branch' => $user->branch?->name,
                    'created_at' => $user->created_at?->format('Y-m-d H:i'),
                    'updated_at' => $user->updated_at?->format('Y-m-d H:i'),
                    'can_manage' => $this->userService->canManage($request->user(), $user),
                ]),
            'roleOptions' => $this->roleOptions(),
            'branchOptions' => $this->branchOptions(),
        ]);
    }

    public function add(Request $request): Response
    {
        return Inertia::render('Core/Users/Add', [
            'roleOptions' => $this->assignableRoles($request->user()),
            'branchOptions' => $this->branchOptions(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated(), $request->user());

        return to_route('users.index')->with('success', __('User saved successfully.'));
    }

    public function edit(Request $request, int $user): Response
    {
        $model = $this->users->findWithRoles($user);

        abort_unless($this->userService->canManage($request->user(), $model), 403);

        return Inertia::render('Core/Users/Edit', [
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'roles' => $model->roles->pluck('id')->values(),
                'branch_id' => $model->branch_id,
            ],
            'roleOptions' => $this->assignableRoles($request->user()),
            'branchOptions' => $this->branchOptions(),
        ]);
    }

    public function update(UserRequest $request, int $user): RedirectResponse
    {
        $model = $this->users->findWithRoles($user);

        abort_unless($this->userService->canManage($request->user(), $model), 403);

        $this->userService->update($model, $request->validated(), $request->user());

        return to_route('users.index')->with('success', __('User updated successfully.'));
    }

    public function destroy(Request $request, int $user): RedirectResponse
    {
        $model = $this->users->findWithRoles($user);

        abort_unless($this->userService->canManage($request->user(), $model), 403);

        try {
            $this->userService->delete($model, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('User deleted successfully.'));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function roleOptions(): array
    {
        return $this->roles->lookup()
            ->map(fn (array $role) => ['value' => $role['id'], 'label' => __($role['name'])])
            ->values()
            ->all();
    }

    /**
     * The Super Admin role is offered to a Super Admin only.
     *
     * @return list<array{value: int, label: string}>
     */
    private function assignableRoles(User $actor): array
    {
        return $this->roles->lookup()
            ->reject(fn (array $role) => $role['name'] === UserRole::SUPER_ADMIN->value && ! $actor->hasRole(UserRole::SUPER_ADMIN->value))
            ->map(fn (array $role) => ['value' => $role['id'], 'label' => __($role['name'])])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function branchOptions(): array
    {
        return $this->branches->query()->with('translations')->orderBy('id')->get()
            ->map(fn (Branch $branch) => ['value' => $branch->id, 'label' => (string) $branch->name])
            ->values()
            ->all();
    }
}
