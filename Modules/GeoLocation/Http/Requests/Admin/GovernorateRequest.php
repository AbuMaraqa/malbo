<?php

namespace Modules\GeoLocation\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;

class GovernorateRequest extends FormRequest
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
                'required', 'string', 'max:10',
                Rule::unique((new Governorate)->getTable(), 'code')->ignore($this->route('governorate')),
            ],
            'country_id' => ['required', 'integer', Rule::exists((new Country)->getTable(), 'id')],
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
            'country_id.required' => __('Country is required'),
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
            'country_id' => __('Country'),
        ];
    }
}
