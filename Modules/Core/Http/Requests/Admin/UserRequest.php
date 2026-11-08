<?php

namespace Modules\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Branch\Entities\Branch;
use Spatie\Permission\Models\Role;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * Login lowers the email it receives and PostgreSQL compares text exactly: a mixed-case
     * address saved here could never log in.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['bail', 'integer', Rule::exists((new Role)->getTable(), 'id')],
            'branch_id' => ['bail', 'nullable', 'integer', Rule::exists((new Branch)->getTable(), 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'password' => __('Password'),
            'roles' => __('Roles'),
            'branch_id' => __('Branches'),
        ];
    }
}
