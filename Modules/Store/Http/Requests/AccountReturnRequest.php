<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestType;

/**
 * A buyer's return or exchange request. The form sends every line with a quantity; the lines left
 * at 0 are dropped before validation. Whether the order, quantities and exchange products are
 * allowed is ReturnRequestService's to say.
 */
class AccountReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'type' => ['required', Rule::enum(ReturnRequestType::class)],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.order_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
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
            'type' => __('Request type'),
            'reason' => __('Reason'),
            'customer_note' => __('Notes'),
        ];
    }
}
