<?php

namespace Modules\Store\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GeoLocation\Entities\City;

class ShippingZoneRequest extends FormRequest
{
    private const MONEY_MAX = 'max:99999999.99'; // decimal(10, 2)

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * The form always sends its city list; a missing list is empty.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['cities' => array_values((array) $this->input('cities', []))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fee' => ['required', 'numeric', 'min:0', self::MONEY_MAX],
            'eta_min_days' => ['required', 'integer', 'min:0', 'max:365'],
            'eta_max_days' => ['required', 'integer', 'min:0', 'max:365', 'gte:eta_min_days'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0', self::MONEY_MAX],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'cities' => ['array', 'max:5000'],
            'cities.*' => ['integer', 'distinct', Rule::exists((new City)->getTable(), 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'fee' => __('Delivery fee'),
            'eta_min_days' => __('Fastest delivery (days)'),
            'eta_max_days' => __('Slowest delivery (days)'),
            'free_shipping_threshold' => __('Free delivery from'),
            'sort_order' => __('Sort Order'),
            'cities' => __('Cities'),
            'cities.*' => __('City'),
        ];
    }
}
