<?php

namespace Modules\Store\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShippingSettingsRequest extends FormRequest
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
        return [
            'default_free_shipping_threshold' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['default_free_shipping_threshold' => __('Free delivery from')];
    }
}
