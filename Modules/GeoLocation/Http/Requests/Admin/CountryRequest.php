<?php

namespace Modules\GeoLocation\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\Currency;
use Modules\GeoLocation\Entities\Country;

class CountryRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'size:2',
                Rule::unique((new Country)->getTable(), 'code')->ignore($this->route('country')),
            ],
            'phone_code' => ['required', 'string', 'max:10'],
            'currency_id' => ['required', 'integer', Rule::exists((new Currency)->getTable(), 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Name is required'),
            'code.required' => __('Code is required'),
            'code.unique' => __('Code already exist'),
            'phone_code.required' => __('Phone Code is required'),
            'currency_id.required' => __('Currency is required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'code' => __('Code'),
            'phone_code' => __('Phone Code'),
            'currency_id' => __('Currency'),
        ];
    }
}
