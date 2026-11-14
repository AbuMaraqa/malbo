<?php

namespace Modules\Wholesale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\GeoLocation\Entities\City;
use Modules\Store\Support\Phone;

/**
 * A shop applies to buy wholesale. The role and the status are not asked: the service gives Merchant
 * and pending. The register's scan is optional: a PDF or an image, 5 MB at most.
 */
class MerchantApplicationRequest extends FormRequest
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
        return [
            'shop_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:120'],
            'city_id' => ['required', 'integer', Rule::exists((new City)->getTable(), 'id')],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'string', 'email', 'max:160'],
            'commercial_register' => ['required', 'string', 'max:60'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'register_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
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

            if (! $validator->errors()->has('email') && app(UserRepositoryInterface::class)->emailTaken((string) $this->input('email'))) {
                $validator->errors()->add('email', __('An account with this email already exists. Sign in instead.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shop_name' => __('Shop name'),
            'owner_name' => __('Owner\'s full name'),
            'city_id' => __('City'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'commercial_register' => __('Commercial register number'),
            'shop_address' => __('Shop address'),
            'register_document' => __('Commercial register document'),
            'password' => __('Password'),
        ];
    }
}
