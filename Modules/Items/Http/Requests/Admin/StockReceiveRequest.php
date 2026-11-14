<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Product;

/**
 * Goods that arrived: a product, how many, and an optional note (a delivery note number). Whether
 * the product holds stock is InventoryService's to say.
 */
class StockReceiveRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists((new Product)->getTable(), 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['product_id' => __('Product'), 'quantity' => __('Quantity'), 'note' => __('Note')];
    }
}
