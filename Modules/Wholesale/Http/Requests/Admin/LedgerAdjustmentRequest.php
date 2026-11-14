<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A correction to the merchant's account: signed (+ the merchant owes more, − less), never 0,
 * always with the reason.
 */
class LedgerAdjustmentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-9999999999.99', 'max:9999999999.99'],
            'entry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('Amount'),
            'entry_date' => __('Date'),
            'reference' => __('Reference'),
            'note' => __('Reason'),
        ];
    }
}
