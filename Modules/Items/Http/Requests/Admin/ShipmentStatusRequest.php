<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Enums\ShipmentStatus;

/**
 * Moves a shipment along, with an optional note. Whether the shipment and its order may make the
 * move is ShipmentService's to say.
 */
class ShipmentStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
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
