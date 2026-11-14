<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A product's wholesale terms: `prices[<item id>][<tier id>]` (empty: no override), `breaks` (each a
 * minimum quantity and a percent, minimums distinct) and `by_piece`. Which items and tiers are the
 * product's is checked by WholesalePriceService.
 */
class ProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['breaks' => array_values((array) $this->input('breaks', []))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'by_piece' => ['required', 'boolean'],
            'prices' => ['array', 'max:500'],
            'prices.*' => ['array'],
            'prices.*.*' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'breaks' => ['array', 'max:20'],
            'breaks.*.min_quantity' => ['required', 'integer', 'min:2', 'max:1000000', 'distinct'],
            // Below 100: a break never gives the goods away.
            'breaks.*.discount_percent' => ['required', 'numeric', 'gt:0', 'lt:100'],
        ];
    }

    /**
     * Breaks rise: more pieces always earn a strictly larger discount, whatever order they are sent
     * in. The break that does not is the one marked.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $breaks = collect((array) $this->input('breaks', []))
                ->map(fn ($break, $index) => ['index' => $index, 'min' => (int) $break['min_quantity'], 'percent' => (float) $break['discount_percent']])
                ->sortBy('min')
                ->values();

            for ($i = 1; $i < $breaks->count(); $i++) {
                if ($breaks[$i]['percent'] <= $breaks[$i - 1]['percent']) {
                    $validator->errors()->add("breaks.{$breaks[$i]['index']}.discount_percent", __('A break for more pieces needs a larger discount than the break for :count pieces.', ['count' => $breaks[$i - 1]['min']]));
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
            'by_piece' => __('Sold by the piece'),
            'prices.*.*' => __('Tier price'),
            'breaks.*.min_quantity' => __('From quantity'),
            'breaks.*.discount_percent' => __('Discount (%)'),
        ];
    }
}
