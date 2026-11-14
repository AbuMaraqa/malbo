<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;

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
        // A storefront order has no customer account, branch or (sometimes) district: editing it
        // must not demand them. An order made here keeps needing them.
        $saved = $id ? app(OrderRepositoryInterface::class)->find($id) : null;
        $required = self::requiredFor($saved);
        $needed = fn (string $column) => $required[$column] ? 'required' : 'nullable';

        return [
            ...($id ? [] : ['submit_token' => ['required', 'uuid']]),
            'user_id' => [$needed('user_id'), 'integer', Rule::exists((new User)->getTable(), 'id')],
            'branch_id' => [$needed('branch_id'), 'integer', Rule::exists((new Branch)->getTable(), 'id')],
            'city_id' => ['required', 'integer', Rule::exists((new City)->getTable(), 'id')],
            'district_id' => [$needed('district_id'), 'integer', Rule::exists((new District)->getTable(), 'id')->where('city_id', (int) $this->input('city_id'))],
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
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    /**
     * Which of the customer, branch and district this form requires: all of them for a new order,
     * and for a saved one only those it already has (a storefront order has none of the first two).
     * The pages mark the same fields as required.
     *
     * @return array{user_id: bool, branch_id: bool, district_id: bool}
     */
    public static function requiredFor(?Order $saved): array
    {
        $has = fn (string $column) => $saved === null || $saved->{$column} !== null;

        return ['user_id' => $has('user_id'), 'branch_id' => $has('branch_id'), 'district_id' => $has('district_id')];
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
            'internal_notes' => __('Internal notes'),
            'customer_name' => __('Customer name'),
            'customer_email' => __('Customer email'),
        ];
    }
}
