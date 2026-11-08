<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;

/**
 * There are deliberately no rules for sub_total, discount, total or an item's price: only what has a
 * rule reaches OrderService, so a figure the browser sends goes nowhere. Whether the status may
 * change is decided by OrderService, against the locked row.
 */
class OrderRequest extends FormRequest
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
        $id = (int) $this->route('order');

        return [
            ...($id ? [] : ['submit_token' => ['required', 'uuid']]),
            'user_id' => ['required', 'integer', Rule::exists((new User)->getTable(), 'id')],
            'branch_id' => ['required', 'integer', Rule::exists((new Branch)->getTable(), 'id')],
            'city_id' => ['required', 'integer', Rule::exists((new City)->getTable(), 'id')],
            'district_id' => ['required', 'integer', Rule::exists((new District)->getTable(), 'id')->where('city_id', (int) $this->input('city_id'))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', Rule::exists((new OrderItem)->getTable(), 'id')->where('order_id', $id ?: -1)->whereNull('deleted_at')],
            // Only a saved line whose product was deleted later may have none.
            'items.*.product_id' => ['nullable', 'required_without:items.*.id', 'integer', Rule::exists((new Product)->getTable(), 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'delivery_type' => ['nullable', Rule::enum(DeliveryType::class)],
            'delivery_address' => ['required', 'string'],
            'contact_phone' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('Customer'),
            'branch_id' => __('Branch'),
            'city_id' => __('City'),
            'district_id' => __('District'),
            'items' => __('Products'),
            'items.*.product_id' => __('Product'),
            'items.*.quantity' => __('Quantity'),
            'delivery_fee' => __('Delivery Fee'),
            'status' => __('Order Status'),
            'payment_method' => __('Payment Method'),
            'payment_status' => __('Payment Status'),
            'delivery_type' => __('Delivery Type'),
            'delivery_address' => __('Delivery Address'),
            'contact_phone' => __('Contact Phone'),
            'notes' => __('Notes'),
        ];
    }
}
