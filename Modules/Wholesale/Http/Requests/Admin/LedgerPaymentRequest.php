<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Wholesale\Enums\LedgerPaymentMethod;

/**
 * A payment from the merchant: the amount it paid (above 0), how, when, a reference, a note, and
 * the receipt (a PDF or an image, kept on the private disk).
 */
class LedgerPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'method' => ['required', Rule::enum(LedgerPaymentMethod::class)],
            'entry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('Amount'),
            'method' => __('Payment method'),
            'entry_date' => __('Date'),
            'reference' => __('Reference'),
            'note' => __('Note'),
            'receipt' => __('Receipt'),
        ];
    }
}
