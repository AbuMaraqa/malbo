<?php

namespace Modules\Store\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Store\Support\Phone;

/**
 * "Notify me when available": the item, and an email or a phone. `website` is the honeypot.
 */
class StockAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'email' => ['nullable', 'required_without:phone', 'string', 'max:160', 'email:rfc'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Phone::plausible((string) $value)) {
                    $fail(__('Enter a valid phone number.'));
                }
            }],
            'website' => ['nullable', 'string', 'max:200'],
        ];
    }
}
