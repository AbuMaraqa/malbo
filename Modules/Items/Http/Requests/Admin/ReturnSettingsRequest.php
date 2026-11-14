<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReturnSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['return_window_days' => ['required', 'integer', 'min:0', 'max:365']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['return_window_days' => __('Days to return after delivery')];
    }
}
