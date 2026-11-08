<?php

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;

class RegisterRequest extends FormRequest
{
    /**
     * Login lowers the email it receives and PostgreSQL compares text exactly.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password'  => 'required|string|min:8|max:255',
            'phone'     => 'required|string|max:255',

            'fcm_token'   => 'sometimes|string',
            'device_name' => 'sometimes|string|max:255',

            // Whoever signs up here is a buyer: staff roles are given in the admin only.
            'role'          => ['sometimes', 'string', Rule::in([UserRole::CUSTOMER->value])],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
