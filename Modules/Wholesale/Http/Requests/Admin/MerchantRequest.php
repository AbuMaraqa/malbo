<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\Wholesale\Entities\Tier;

/**
 * A merchant's profile, tier, sales rep and minimum order. The status changes on its own route.
 */
class MerchantRequest extends FormRequest
{
    private const MONEY_MAX = 'max:99999999.99'; // decimal(10, 2)

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
            'shop_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:120'],
            'city_id' => ['nullable', 'integer', Rule::exists((new City)->getTable(), 'id')],
            'phone' => ['required', 'string', 'max:40'],
            'commercial_register' => ['required', 'string', 'max:60'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'tier_id' => ['nullable', 'integer', Rule::exists((new Tier)->getTable(), 'id')],
            'sales_rep_id' => ['nullable', 'integer', Rule::exists((new User)->getTable(), 'id')],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0', self::MONEY_MAX],
            'minimum_order_pieces' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            // Left out: the merchant's credit stays as it is.
            'credit_enabled' => ['sometimes', 'boolean'],
            'credit_limit' => ['nullable', 'required_if_accepted:credit_enabled', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * What the staff member may change: the credit fields only with `Merchant Credit Update`
     * (without it they are left out, and the merchant's credit stays as it is).
     *
     * @return array<string, mixed>
     */
    public function merchantData(): array
    {
        $data = $this->validated();

        if (! $this->user()?->can('Merchant Credit Update')) {
            unset($data['credit_enabled'], $data['credit_limit']);
        }

        return $data;
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
            'credit_enabled' => __('Buys on credit'),
            'credit_limit' => __('Credit limit'),
            'phone' => __('Phone'),
            'commercial_register' => __('Commercial register number'),
            'shop_address' => __('Shop address'),
            'tier_id' => __('Price tier'),
            'sales_rep_id' => __('Sales rep'),
            'minimum_order_value' => __('Minimum order value'),
            'minimum_order_pieces' => __('Minimum order pieces'),
        ];
    }
}
