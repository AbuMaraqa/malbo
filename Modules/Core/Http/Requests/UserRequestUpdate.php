<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\User;

class UserRequestUpdate extends FormRequest
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
            'name'            => 'sometimes|required|string|max:255',
            'email'           => ['sometimes', 'required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->route('user'))],
            'phone'           => 'sometimes|required|numeric|regex:/^\+?[0-9]{10,15}$/',
            'password'        => 'sometimes|required|string|min:8|max:255',

            'avatar'        => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo'   => 'sometimes|image|mimes:jpeg,png,jpg|max:4096',
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
