<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Enums\ReturnRequestStatus;

/**
 * Moves a request along: approve, reject, receive or complete, with an optional note. Whether the
 * move is allowed from where the request stands is ReturnRequestService's to say.
 */
class ReturnRequestStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                ReturnRequestStatus::APPROVED->value,
                ReturnRequestStatus::REJECTED->value,
                ReturnRequestStatus::RECEIVED->value,
                ReturnRequestStatus::COMPLETED->value,
            ])],
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
