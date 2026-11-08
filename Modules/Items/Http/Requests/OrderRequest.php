<?php

namespace Modules\Items\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\PaymentMethod;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $addonOptionsTable = config('items.table_prefix') . 'addon_options';
        $productsTable = config('items.table_prefix') . 'products';
        $branchesTable = config('branch.table_prefix') . 'branches';

        return [
            'delivery_address' => ['nullable', 'numeric', 'max:255'],
            'contact_phone'    => ['nullable', 'string', 'max:20'],
            'branch_id'        => ['required', 'integer', 'exists:' . $branchesTable . ',id'],
            'payment_method'   => ['required', new \Illuminate\Validation\Rules\Enum(PaymentMethod::class)],
            'notes'            => ['nullable', 'string'],
            'delivery_type'    => ['required', new \Illuminate\Validation\Rules\Enum(DeliveryType::class)],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:' . $productsTable . ',id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:1000'],

            'items.*.addons'   => 'nullable|array',
            'items.*.addons.*.addon_option_id' => 'required|integer|exists:' . $addonOptionsTable . ',id',
            'items.*.addons.*.quantity' => 'required|integer|min:1|max:1000',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
