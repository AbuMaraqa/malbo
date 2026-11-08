<?php

namespace Modules\GeoLocation\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Enums\DistrictType;

class DistrictRequest extends FormRequest
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
            'city_id' => ['required', 'integer', Rule::exists((new City)->getTable(), 'id')],
            'type' => ['required', Rule::enum(DistrictType::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Name is required'),
            'city_id.required' => __('City is required'),
            'type.required' => __('Type is required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'city_id' => __('City'),
            'type' => __('Type'),
            'latitude' => __('Latitude'),
            'longitude' => __('Longitude'),
        ];
    }
}
