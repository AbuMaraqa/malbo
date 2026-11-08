<?php

namespace Modules\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\Currency;
use Modules\Core\Enums\SymbolPosition;

class CurrencyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'plural_name' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'size:3',
                Rule::unique((new Currency)->getTable(), 'code')->ignore($this->route('currency')),
            ],
            'symbol' => ['required', 'string', 'max:10'],
            'symbol_position' => ['required', Rule::enum(SymbolPosition::class)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'code' => __('Code'),
            'symbol' => __('Symbol'),
            'symbol_position' => __('Position'),
            'decimal_places' => __('Decimal places'),
            'exchange_rate' => __('Exchange rate'),
        ];
    }
}
