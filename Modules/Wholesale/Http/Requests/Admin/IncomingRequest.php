<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A product's incoming goods: `incoming[<item id>]` with a quantity and the expected date (needed
 * when the quantity is above 0, today or later), and what the page showed when it was opened
 * (`loaded_quantity`, `loaded_expected_at`): a row changed since is refused. The page sends only the
 * rows that were changed. Which items are the product's is checked by WholesalePriceService.
 */
class IncomingRequest extends FormRequest
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
            'incoming' => ['required', 'array', 'max:500'],
            'incoming.*' => ['array'],
            'incoming.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'incoming.*.expected_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'incoming.*.loaded_quantity' => ['required', 'integer', 'min:0'],
            'incoming.*.loaded_expected_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('incoming', []) as $id => $row) {
                if ((int) ($row['quantity'] ?? 0) > 0 && blank($row['expected_at'] ?? null) && ! $validator->errors()->has("incoming.{$id}.quantity")) {
                    $validator->errors()->add("incoming.{$id}.expected_at", __('Enter the date the goods are expected.'));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'incoming.*.quantity' => __('Incoming quantity'),
            'incoming.*.expected_at' => __('Expected on'),
        ];
    }
}
