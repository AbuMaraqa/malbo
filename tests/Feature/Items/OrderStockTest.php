<?php

namespace Tests\Feature\Items;

use Modules\Items\Entities\Order;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\StockMovementType;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * Orders written in the admin move stock: a sale takes it, an edit moves only the difference, a
 * cancellation or return gives it back once.
 */
class OrderStockTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrders();
    }

    public function test_creating_an_order_takes_its_stock(): void
    {
        $product = $this->stocked(10);

        $this->storeOrder([['product_id' => $product->id, 'quantity' => 3]])->assertSessionHasNoErrors();

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $movement = StockMovement::query()->sole();
        $this->assertSame(StockMovementType::SALE, $movement->type);
        $this->assertSame($this->lastOrder()->id, $movement->order_id);
        $this->assertSame($this->staff->id, $movement->created_by);
    }

    public function test_an_order_beyond_stock_is_refused_and_nothing_is_saved(): void
    {
        $product = $this->stocked(2);

        $this->storeOrder([['product_id' => $product->id, 'quantity' => 3]])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_editing_a_quantity_moves_only_the_difference(): void
    {
        $product = $this->stocked(10);
        $order = $this->placed($product, 3);
        $line = $order->items()->firstOrFail();

        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 5]])->assertSessionHasNoErrors();
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $this->updateOrder($order->fresh(), [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_raising_a_quantity_beyond_stock_is_refused(): void
    {
        $product = $this->stocked(4);
        $order = $this->placed($product, 3);
        $line = $order->items()->firstOrFail();

        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 6]])->assertSessionHasErrors('items');

        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(3, $line->fresh()->quantity);
    }

    public function test_removing_a_line_or_changing_its_product_gives_the_old_stock_back(): void
    {
        $first = $this->stocked(10);
        $second = $this->stocked(10);
        $order = $this->placed($first, 4);
        $line = $order->items()->firstOrFail();

        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $second->id, 'quantity' => 4]])->assertSessionHasNoErrors();

        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(6, $second->fresh()->stock_quantity);
    }

    public function test_cancelling_gives_the_stock_back_once(): void
    {
        $product = $this->stocked(10);
        $order = $this->placed($product, 3);
        $line = $order->items()->firstOrFail();
        $items = [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 3]];

        $this->updateOrder($order, $items, ['status' => OrderStatus::CANCELLED->value])->assertSessionHasNoErrors();
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->updateOrder($order->fresh(), $items, ['status' => OrderStatus::CANCELLED->value])->assertSessionHasNoErrors();
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::CANCEL)->count());
    }

    public function test_returned_gives_the_stock_back_as_return(): void
    {
        $product = $this->stocked(10);
        $order = $this->placed($product, 2);
        $order->update(['status' => OrderStatus::OUT_FOR_DELIVERY]);
        $line = $order->items()->firstOrFail();

        $this->updateOrder($order->fresh(), [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 2]], ['status' => OrderStatus::RETURNED->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::query()->where('type', StockMovementType::RETURN)->value('quantity'));
    }

    public function test_lines_of_a_final_order_are_not_restocked_again_on_edit(): void
    {
        $product = $this->stocked(10);
        $order = $this->placed($product, 3);
        $line = $order->items()->firstOrFail();

        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 3]], ['status' => OrderStatus::CANCELLED->value]);
        $this->updateOrder($order->fresh(), [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 1]], ['status' => OrderStatus::CANCELLED->value]);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_deleting_an_order_gives_its_stock_back(): void
    {
        $product = $this->stocked(10);
        $order = $this->placed($product, 4);

        $this->actingAs($this->staff)->delete($this->siteUrl('/ar/admin/orders/'.$order->id))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($order);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(4, StockMovement::query()->where('type', StockMovementType::CANCEL)->sole()->quantity);
    }

    public function test_bulk_delete_gives_the_stock_back(): void
    {
        $product = $this->stocked(10);
        $first = $this->placed($product, 2);
        $second = $this->placed($product, 3);

        $this->actingAs($this->staff)->delete($this->siteUrl('/ar/admin/orders'), ['ids' => [$first->id, $second->id]])->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_deleting_a_cancelled_order_does_not_give_the_stock_back_twice(): void
    {
        $product = $this->stocked(10);
        $order = $this->placed($product, 3);
        $line = $order->items()->firstOrFail();
        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 3]], ['status' => OrderStatus::CANCELLED->value])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->delete($this->siteUrl('/ar/admin/orders/'.$order->id))->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    private function placed($product, int $quantity): Order
    {
        $this->storeOrder([['product_id' => $product->id, 'quantity' => $quantity]])->assertSessionHasNoErrors();

        return $this->lastOrder();
    }
}
