<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;
use Modules\Store\Services\CheckoutService;
use Modules\Store\Support\Phone;

/**
 * A guest's checkout form. There is deliberately no rule for a price, a fee or a total: only what
 * has a rule reaches CheckoutService, which works out the money itself. Whether the city gets
 * delivery is decided there, against the zones.
 */
class CheckoutRequest extends FormRequest
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
            'submit_token' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'city_id' => ['required', 'integer', Rule::exists((new City)->getTable(), 'id')],
            'district_id' => [
                Rule::requiredIf(fn () => $cityId > 0 && app(DistrictRepositoryInterface::class)->query()->where('city_id', $cityId)->exists()),
                'nullable', 'integer',
                Rule::exists((new District)->getTable(), 'id')->where('city_id', $cityId),
            ],
            'address' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::in(array_column(app(CheckoutService::class)->paymentMethods(), 'value'))],
            // Signed in: keep this address in the account (ignored for guests).
            'save_address' => ['nullable', 'boolean'],
            'address_label' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $digits = strlen(Phone::normalize((string) $this->input('phone')));

            if (! $validator->errors()->has('phone') && ($digits < 7 || $digits > 12)) {
                $validator->errors()->add('phone', __('Enter a valid phone number.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Full name'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'city_id' => __('City'),
            'district_id' => __('Area'),
            'address' => __('Address'),
            'notes' => __('Notes'),
            'coupon' => __('Coupon'),
            'payment_method' => __('Payment method'),
        ];
    }
}
