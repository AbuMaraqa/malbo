<?php

namespace Modules\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique((new Role)->getTable(), 'name')->where('guard_name', 'web')->ignore($this->route('role')),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['bail', 'integer', Rule::exists((new Permission)->getTable(), 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Role Name'),
            'permissions' => __('Permissions'),
        ];
    }
}
