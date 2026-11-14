<?php

namespace Modules\Wholesale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The pieces of each size of one product: `quantities[<item id>] = <pieces>`. Which sizes belong to
 * the product, stock and size runs are checked by WholesaleCartService.
 */
class CartQuantitiesRequest extends FormRequest
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
            'quantities' => ['present', 'array', 'max:200'],
            'quantities.*' => ['integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ((array) $this->validated('quantities', []) as $id => $quantity) {
            $quantities[(int) $id] = (int) $quantity;
        }

        return $quantities;
    }
}
