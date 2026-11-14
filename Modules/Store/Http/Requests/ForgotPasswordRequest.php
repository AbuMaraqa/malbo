<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
        return ['email' => ['required', 'string', 'email', 'max:160']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __('Email')];
    }
}
