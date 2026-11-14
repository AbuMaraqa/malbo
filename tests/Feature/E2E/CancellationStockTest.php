<?php

namespace Tests\Feature\E2E;

use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ShipmentStatus;

/**
 * Whatever stage an order is stopped at, its stock comes back exactly once: cancelled while
 * awaiting confirmation, confirmed or being prepared (its pending shipment cancelled with it),
 * rejected, returned by the courier or returned after delivery; a second submit changes nothing,
 * and an order with the courier cannot be cancelled.
 */
class CancellationStockTest extends E2ETestCase
{
    public function test_every_stop_gives_the_stock_back_once(): void
    {
        $item = $this->demo()->item('oxford-shirt', 'white', 'M');
        $start = $this->stock($item);

        $cases = [
            'awaiting → cancelled' => [[], OrderStatus::CANCELLED, 'cancel'],
            'awaiting → rejected' => [[], OrderStatus::REJECTED, 'cancel'],
            'confirmed → cancelled' => [[OrderStatus::CONFIRMED], OrderStatus::CANCELLED, 'cancel'],
            'preparing → cancelled' => [[OrderStatus::CONFIRMED, OrderStatus::PREPARING], OrderStatus::CANCELLED, 'cancel'],
        ];

        foreach ($cases as $label => [$before, $stop, $type]) {
            $order = $this->orderOf($item, 2);
            $this->assertSame($start - 2, $this->stock($item), $label.': taken at checkout');

            foreach ($before as $status) {
                $this->adminStatus($order, $status)->assertSessionHasNoErrors();
            }

            if (in_array(OrderStatus::PREPARING, $before, true)) {
                $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/shipments'), ['order_id' => $order->id, 'courier' => 'manual'])->assertSessionHasNoErrors();
            }

            $this->adminStatus($order, $stop)->assertSessionHasNoErrors();
            $this->adminStatus($order, $stop)->assertSessionHasNoErrors(); // submitted twice

            $this->assertSame($stop, $order->fresh()->status, $label);
            $this->assertSame($start, $this->stock($item), $label.': back once');
            $this->assertSame(['sale' => -2, $type => 2], $this->movements($order), $label);

            if (in_array(OrderStatus::PREPARING, $before, true)) {
                $this->assertSame(ShipmentStatus::CANCELLED, Shipment::query()->where('order_id', $order->id)->sole()->status, 'its pending shipment is cancelled');
            }
        }
    }

    public function test_with_the_courier_it_cannot_be_cancelled_but_a_return_to_sender_restocks_once(): void
    {
        $item = $this->demo()->item('chinos', 'beige', '34');
        $start = $this->stock($item);
        $order = $this->orderOf($item, 1);

        $this->adminStatus($order, OrderStatus::CONFIRMED)->assertSessionHasNoErrors();
        $this->adminStatus($order, OrderStatus::PREPARING)->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/shipments'), ['order_id' => $order->id, 'courier' => 'manual'])->assertSessionHasNoErrors();
        $shipment = Shipment::query()->where('order_id', $order->id)->sole();
        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);

        $this->adminStatus($order, OrderStatus::CANCELLED)->assertSessionHasErrors('status');
        $this->assertSame($start - 1, $this->stock($item));

        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'returned_to_sender'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->status);
        $this->adminStatus($order, OrderStatus::RETURNED)->assertSessionHasNoErrors();
        $this->assertSame($start, $this->stock($item));
        $this->assertSame(['sale' => -1, 'return' => 1], $this->movements($order));
    }

    public function test_a_delivered_order_returned_whole_restocks_once(): void
    {
        $item = $this->demo()->item('polo', 'navy', 'M');
        $start = $this->stock($item);
        $order = $this->orderOf($item, 3);

        $this->deliverInAdmin($order);
        $this->adminStatus($order, OrderStatus::RETURNED)->assertSessionHasNoErrors();
        $this->adminStatus($order, OrderStatus::RETURNED)->assertSessionHasNoErrors();

        $this->assertSame($start, $this->stock($item));
        $this->assertSame(['sale' => -3, 'return' => 3], $this->movements($order));
        $this->adminStatus($order, OrderStatus::DELIVERED)->assertSessionHasErrors('status');
    }

    private function orderOf(Product $item, int $quantity): Order
    {
        $this->checkout($this->addToCart($item, $quantity))->assertSessionHasNoErrors();

        return $this->lastOrder();
    }
}
