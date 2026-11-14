<?php

namespace Modules\Wholesale\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * A status change and its note. Which changes are allowed, and when a note is needed, is decided by
 * MerchantService on the locked row.
 */
class MerchantStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(MerchantStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => __('Status'), 'note' => __('Note')];
    }
}
