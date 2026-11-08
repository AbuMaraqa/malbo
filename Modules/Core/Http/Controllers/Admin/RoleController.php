<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Http\Requests\Admin\RoleRequest;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Core\Services\RoleService;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Core\Support\PermissionLabels;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly RoleService $roleService,
    ) {}

    public function index(): Response
    {
        $permissions = $this->roles->allPermissions();

        return Inertia::render('Core/Roles/Index', [
            'table' => AdminTable::for($this->roles->tableQuery())
                ->search(['name'], null)
                ->filters(
                    AdminFilter::relation('permission', 'permissions'),
                    AllowedFilter::callback('has_users', function (Builder $query, mixed $value): void {
                        filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->whereHas('users') : $query->whereDoesntHave('users');
                    }),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('name', 'users_count', 'created_at', 'id')
                ->defaultSort('name')
                ->rows(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => __($role->name),
                    'users_count' => $role->users_count,
                    'summary' => PermissionLabels::summary($role->permissions->toBase()),
                    'permissions' => $role->permissions->pluck('id')->values(),
                    'protected' => $this->roleService->isProtected($role),
                    'editable' => ! $this->roleService->isSuperAdmin($role),
                ]),
            'permissionGroups' => PermissionLabels::groups($permissions),
            'permissionOptions' => $permissions
                ->map(fn (Permission $permission) => ['value' => $permission->id, 'label' => PermissionLabels::label($permission->name)])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->roleService->create($data['name'], $data['permissions'] ?? []);

        return back()->with('success', __('Role saved successfully.'));
    }

    public function update(RoleRequest $request, int $role): RedirectResponse
    {
        /** @var Role $model */
        $model = $this->roles->findOrFail($role);
        $data = $request->validated();

        $this->roleService->update($model, $data['name'], $data['permissions'] ?? []);

        return back()->with('success', __('Role updated successfully.'));
    }

    public function destroy(int $role): RedirectResponse
    {
        /** @var Role $model */
        $model = $this->roles->findOrFail($role);

        try {
            $this->roleService->delete($model);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Role deleted successfully.'));
    }
}
