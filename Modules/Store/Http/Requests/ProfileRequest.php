<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Store\Support\Phone;

class ProfileRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            // A new email is a new way into the account (reset links go to it): the password first.
            'current_password' => [Rule::requiredIf(fn () => $this->changesEmail()), 'nullable', 'string', 'current_password:web'],
        ];
    }

    public function changesEmail(): bool
    {
        return mb_strtolower(trim((string) $this->input('email'))) !== mb_strtolower((string) $this->user()->email);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => __('Enter your current password to change your email.'),
            'current_password.current_password' => __('The current password is not correct.'),
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

            if (! $validator->errors()->has('email') && app(UserRepositoryInterface::class)->emailTaken((string) $this->input('email'), (int) $this->user()->id)) {
                $validator->errors()->add('email', __('Another account already uses this email.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('Full name'), 'email' => __('Email'), 'phone' => __('Phone')];
    }
}
