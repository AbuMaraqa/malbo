<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Support\Phone;

/**
 * A saved address. Its city is one the store delivers to (in an active shipping zone), and its
 * area belongs to that city (required when the city has areas), as at checkout.
 */
class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cityId = (int) $this->input('city_id');

        return [
            'label' => ['nullable', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'city_id' => ['required', 'integer'],
            'district_id' => [
                Rule::requiredIf(fn () => $cityId > 0 && app(DistrictRepositoryInterface::class)->query()->where('city_id', $cityId)->exists()),
                'nullable', 'integer',
                Rule::exists((new District)->getTable(), 'id')->where('city_id', $cityId),
            ],
            'address_line' => ['required', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('phone') && ! Phone::plausible((string) $this->input('phone'))) {
                $validator->errors()->add('phone', __('Enter a valid phone number.'));
            }

            $deliverable = array_column(app(ShippingZoneRepositoryInterface::class)->deliverableCities(), 'value');

            if (! $validator->errors()->has('city_id') && ! in_array((int) $this->input('city_id'), $deliverable, true)) {
                $validator->errors()->add('city_id', __('We do not deliver to this city yet.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => __('Address name'),
            'recipient_name' => __('Full name'),
            'phone' => __('Phone'),
            'city_id' => __('City'),
            'district_id' => __('Area'),
            'address_line' => __('Address'),
        ];
    }
}
