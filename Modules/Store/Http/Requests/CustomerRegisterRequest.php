<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Store\Support\Phone;

/**
 * A buyer signs up on the storefront. The role is not asked: CustomerAuthService gives Customer.
 */
class CustomerRegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('phone') && ! Phone::plausible((string) $this->input('phone'))) {
                $validator->errors()->add('phone', __('Enter a valid phone number.'));
            }

            // Any letter case: Buyer@ and buyer@ are one account.
            if (! $validator->errors()->has('email') && app(UserRepositoryInterface::class)->emailTaken((string) $this->input('email'))) {
                $validator->errors()->add('email', __('An account with this email already exists. Sign in instead.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Full name'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'password' => __('Password'),
        ];
    }
}
