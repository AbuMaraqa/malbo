<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A product and a quantity for the cart. Whether the product is for sale, and the quantity
 * allowed, are decided by CartService.
 */
class CartItemRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            // A set or bundle: component id => the variant chosen (CartService checks them).
            'components' => ['nullable', 'array', 'max:20'],
            'components.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['product_id' => __('Product'), 'quantity' => __('Quantity')];
    }
}
