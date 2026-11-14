<?php

namespace Modules\Wholesale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Wholesale\Enums\PaymentTerms;
use Modules\Wholesale\Services\WholesaleCheckoutService;

class WholesaleCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `merchant.approved` decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_source' => ['required', Rule::in([WholesaleCheckoutService::SHOP, WholesaleCheckoutService::SAVED])],
            'address_id' => ['nullable', 'required_if:address_source,'.WholesaleCheckoutService::SAVED, 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_terms' => ['required', Rule::enum(PaymentTerms::class)],
            'submit_token' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address_source' => __('Delivery address'),
            'address_id' => __('Delivery address'),
            'notes' => __('Notes'),
            'payment_terms' => __('Payment terms'),
        ];
    }
}
