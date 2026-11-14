<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WholesaleSettingsRequest extends FormRequest
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
            'minimum_order_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'minimum_order_pieces' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'minimum_order_value' => __('Minimum order value'),
            'minimum_order_pieces' => __('Minimum order pieces'),
        ];
    }
}
