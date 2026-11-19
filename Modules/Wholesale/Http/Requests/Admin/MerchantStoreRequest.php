<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Entities\User;

/**
 * A merchant staff add from the merchants list: the profile and terms of MerchantRequest, and the
 * account it signs in with (an email no account has yet, and the password staff hand over).
 */
class MerchantStoreRequest extends MerchantRequest
{
    /** One address is one account, whatever its letters' case. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['required', 'email', 'max:190', Rule::unique((new User)->getTable(), 'email')],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('An account with this email already exists.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'email' => __('Email'),
            'password' => __('Password'),
        ];
    }
}
