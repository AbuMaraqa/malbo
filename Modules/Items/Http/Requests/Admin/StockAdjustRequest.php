<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Product;

/**
 * A correction by hand: a product, a signed change (never 0) and why. Whether the stock may go
 * that low is InventoryService's to say, on the locked row.
 */
class StockAdjustRequest extends FormRequest
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
            'delta' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['product_id' => __('Product'), 'delta' => __('Change'), 'reason' => __('Reason')];
    }
}
