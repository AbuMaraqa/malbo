<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Joining the store's members circle, from the home page form or the app.
 */
class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9\s\-()]{7,}$/'],
            'email' => ['nullable', 'email', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Please write your name.'),
            'phone.required' => __('Please write your phone number.'),
            'phone.regex' => __('Please check the phone number.'),
            'email.email' => __('Please check the email address.'),
            '*.max' => __('This is too long.'),
        ];
    }
}
