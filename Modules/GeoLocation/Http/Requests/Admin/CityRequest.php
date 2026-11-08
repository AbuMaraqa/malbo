<?php

namespace Modules\GeoLocation\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Enums\CapitalType;
use Modules\GeoLocation\Enums\CityType;

class CityRequest extends FormRequest
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
            'governorate_id' => ['required', 'integer', Rule::exists((new Governorate)->getTable(), 'id')],
            'population' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'type' => ['required', Rule::enum(CityType::class)],
            'is_capital' => ['boolean'],
            'capital_type' => ['nullable', 'required_if:is_capital,true', Rule::enum(CapitalType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Name is required'),
            'governorate_id.required' => __('Governorate is required'),
            'type.required' => __('Type is required'),
            'capital_type.required_if' => __('Capital Type is required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'governorate_id' => __('Governorate'),
            'population' => __('Population'),
            'latitude' => __('Latitude'),
            'longitude' => __('Longitude'),
            'type' => __('Type'),
            'capital_type' => __('Capital Type'),
        ];
    }
}
