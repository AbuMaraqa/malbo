<?php

namespace Modules\Branch\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Enums\WeekDay;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;

class BranchRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'country_id' => ['required', 'integer', Rule::exists((new Country)->getTable(), 'id')],
            'city_id' => ['required', 'integer', Rule::exists((new City)->getTable(), 'id'), $this->cityIsInTheCountry(...)],
            'status' => ['required', Rule::enum(BranchStatus::class)],
            'working_hours' => ['array', 'max:7'],
            'working_hours.*.day' => ['required', Rule::enum(WeekDay::class), 'distinct'],
            'working_hours.*.is_closed' => ['boolean'],
            'working_hours.*.start_time' => ['nullable', 'date_format:H:i', 'required_if:working_hours.*.is_closed,false'],
            'working_hours.*.end_time' => ['nullable', 'date_format:H:i', 'required_if:working_hours.*.is_closed,false'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'description' => __('Description'),
            'address' => __('Address'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'latitude' => __('Latitude'),
            'longitude' => __('Longitude'),
            'opening_time' => __('Opening Time'),
            'closing_time' => __('Closing Time'),
            'country_id' => __('Country'),
            'city_id' => __('City'),
            'status' => __('Status'),
            'working_hours.*.start_time' => __('Start'),
            'working_hours.*.end_time' => __('End'),
        ];
    }

    private function cityIsInTheCountry(string $attribute, mixed $value, Closure $fail): void
    {
        $country = $this->input('country_id');

        if (is_numeric($value) && is_numeric($country) && ! app(CityRepositoryInterface::class)->isInCountry((int) $value, (int) $country)) {
            $fail(__('The city is not in the chosen country'));
        }
    }
}
