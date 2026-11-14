<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * A new password from the account page: the current one first.
 */
class AccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['current_password.current_password' => __('The current password is not correct.')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => __('Current password'), 'password' => __('New password')];
    }
}
