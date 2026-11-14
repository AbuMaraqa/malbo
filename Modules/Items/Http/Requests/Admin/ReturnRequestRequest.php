<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestType;

/**
 * A request staff make for a delivered order. Lines left at 0 are dropped before validation. The
 * order's state, the quantities and the exchange products are ReturnRequestService's to check.
 */
class ReturnRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $this->merge(['lines' => array_values(array_filter($lines, fn ($line) => is_array($line) && (int) ($line['quantity'] ?? 0) > 0))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists((new Order)->getTable(), 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(ReturnRequestType::class)],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'staff_note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.order_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.exchange_product_id' => ['nullable', 'integer', Rule::requiredIf(fn () => $this->input('type') === ReturnRequestType::EXCHANGE->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => __('Choose at least one item to send back.'),
            'lines.*.exchange_product_id.required' => __('Choose what to exchange this item for.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_id' => __('Order'),
            'type' => __('Request type'),
            'reason' => __('Reason'),
            'customer_note' => __('Customer note'),
            'staff_note' => __('Staff note'),
        ];
    }
}
