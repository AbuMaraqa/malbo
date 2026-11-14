<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff's own fields on a request: their note and whether the refund was paid.
 */
class ReturnRequestUpdateRequest extends FormRequest
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
            'staff_note' => ['nullable', 'string', 'max:2000'],
            'refunded' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['staff_note' => __('Staff note'), 'refunded' => __('Refunded')];
    }
}
