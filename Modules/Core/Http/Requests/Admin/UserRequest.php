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
            // Unique whatever the letter case (a unique index on lower(email) backs it).
            'email' => ['required', 'email', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $ignore = is_numeric($this->route('user')) ? (int) $this->route('user') : null;

                if (is_string($value) && app(\Modules\Core\Repositories\Contracts\UserRepositoryInterface::class)->emailTaken($value, $ignore)) {
                    $fail(__('Another account already uses this email.'));
                }
            }],
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
