<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Notifications\NewOrderAlert;
use Modules\Items\Services\OrderService;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * Every status an order reaches is written down with its date: the tracking page's timeline.
 */
class OrderStatusHistoryTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrders();
    }

    public function test_every_way_an_order_starts_writes_its_first_status(): void
    {
        $product = $this->stocked(10);

        $this->storeOrder([['product_id' => $product->id, 'quantity' => 1]], ['status' => OrderStatus::CONFIRMED->value])->assertSessionHasNoErrors();
        $this->assertSame([OrderStatus::CONFIRMED], $this->history($this->lastOrder()));

        $placed = app(OrderService::class)->place([
            'order_number' => 'API-1',
            'payment_method' => PaymentMethod::CASH->value,
            'delivery_address' => 'عنوان',
            'contact_phone' => '0599',
            'sub_total' => 100,
            'total' => 100,
        ], [['product_id' => $product->id, 'quantity' => 1, 'price' => 100.0, 'total_price' => 100.0, 'notes' => null, 'options_to_attach' => []]], $this->customer->id);
        $this->assertSame([OrderStatus::AWAITING_CONFIRMATION], $this->history($placed));

        $guest = app(OrderService::class)->checkout($this->checkoutHeader(), [['product_id' => $product->id, 'quantity' => 1, 'price' => 100.0]]);
        $this->assertSame([OrderStatus::AWAITING_CONFIRMATION], $this->history($guest));
    }

    public function test_a_status_change_writes_a_row_and_staying_does_not(): void
    {
        $product = $this->stocked(10);
        $this->storeOrder([['product_id' => $product->id, 'quantity' => 1]]);
        $order = $this->lastOrder();
        $line = ['id' => $order->items()->first()->id, 'product_id' => $product->id, 'quantity' => 1];

        $this->updateOrder($order, [$line])->assertSessionHasNoErrors();
        $this->updateOrder($order, [$line], ['status' => OrderStatus::CONFIRMED->value])->assertSessionHasNoErrors();
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::PREPARING, $this->staff->id);
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::PREPARING, $this->staff->id);

        $this->assertSame([OrderStatus::PENDING, OrderStatus::CONFIRMED, OrderStatus::PREPARING], $this->history($order));
        $this->assertSame($this->staff->id, $order->statusHistory()->latest('id')->first()->created_by);
    }

    public function test_checkout_writes_a_guest_order(): void
    {
        [, $variant] = $this->shirtWithVariant(5);

        $order = app(OrderService::class)->checkout($this->checkoutHeader(['delivery_fee' => 20, 'discount' => 10, 'coupon_code' => 'SAVE10']), [
            ['product_id' => $variant->id, 'quantity' => 2, 'price' => 90.0],
        ]);

        $order->refresh();
        $this->assertNull($order->user_id);
        $this->assertNull($order->created_by);
        $this->assertNull($order->branch_id);
        $this->assertSame(OrderChannel::B2C, $order->channel);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
        $this->assertSame(PaymentStatus::UNPAID, $order->payment_status);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame(['180.00', '10.00', '20.00', '190.00'], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total]);
        $this->assertMatchesRegularExpression('/^\d+$/', $order->order_number);

        $item = $order->items()->first();
        $this->assertSame('SHIRT-BLK-L', $item->sku);
        $this->assertSame('180.00', $item->total_price);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
    }

    public function test_checkout_shortage_saves_nothing(): void
    {
        $product = $this->stocked(1);

        try {
            app(OrderService::class)->checkout($this->checkoutHeader(), [['product_id' => $product->id, 'quantity' => 2, 'price' => 100.0]]);
            $this->fail('A shortage must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public function test_new_order_alert_reaches_order_staff_and_never_fails(): void
    {
        Notification::fake();
        $clerk = User::forceCreate(['name' => 'Clerk', 'email' => 'clerk@orders.test', 'password' => 'secret-password'])->givePermissionTo('Order View List');
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@orders.test', 'password' => 'secret-password']);

        $order = app(OrderService::class)->checkout($this->checkoutHeader(), [['product_id' => $this->stocked(5)->id, 'quantity' => 1, 'price' => 100.0]]);
        NewOrderAlert::send($order);

        Notification::assertSentTo($clerk, GeneralNotification::class);
        Notification::assertNotSentTo($other, GeneralNotification::class);
    }

    /**
     * @return list<OrderStatus>
     */
    private function history(Order $order): array
    {
        return $order->statusHistory()->orderBy('id')->get()->pluck('status')->all();
    }

    private function checkoutHeader(array $overrides = []): array
    {
        return [
            'customer_name' => 'Guest Buyer',
            'customer_email' => null,
            'contact_phone' => '0599123456',
            'delivery_address' => 'شارع 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'notes' => null,
            'payment_method' => PaymentMethod::CASH->value,
            'delivery_fee' => 0,
            'discount' => 0,
            'coupon_code' => null,
            ...$overrides,
        ];
    }
}
