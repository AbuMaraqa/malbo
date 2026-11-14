<?php

namespace Modules\Store\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Store\Support\Phone;

/**
 * The contact typed at checkout, sent as the buyer leaves the field, with whether they ticked
 * "Remind me about my cart".
 */
class CartContactRequest extends FormRequest
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
            'remind' => ['required', 'boolean'],
            'email' => ['nullable', 'string', 'max:160', 'email:rfc'],
            'phone' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Phone::plausible((string) $value)) {
                    $fail(__('Enter a valid phone number.'));
                }
            }],
        ];
    }
}
