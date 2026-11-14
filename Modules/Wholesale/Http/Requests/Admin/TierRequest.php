<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TierRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Below 100: a tier never gives the goods away.
            'discount_percent' => ['required', 'numeric', 'min:0', 'lt:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'discount_percent' => __('Discount off the wholesale price (%)'),
            'sort_order' => __('Sort Order'),
            'is_default' => __('Default tier'),
        ];
    }
}
