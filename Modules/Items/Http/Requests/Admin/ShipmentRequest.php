<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Order;
use Modules\Items\Services\Couriers\CourierRegistry;

/**
 * A new shipment for an order: the courier, and what staff know of it. Whether the order can be
 * shipped now is ShipmentService's to say.
 */
class ShipmentRequest extends FormRequest
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
            'order_id' => ['required', 'integer', Rule::exists((new Order)->getTable(), 'id')->whereNull('deleted_at')],
            'courier' => ['required', 'string', Rule::in(array_column(app(CourierRegistry::class)->options(), 'value'))],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_id' => __('Order'),
            'courier' => __('Courier'),
            'tracking_number' => __('Tracking number'),
            'cost' => __('Shipping cost'),
            'notes' => __('Notes'),
        ];
    }
}
