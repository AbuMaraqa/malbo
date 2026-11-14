<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Exceptions\InsufficientStock;
use Modules\Items\Services\InventoryService;
use Modules\Items\Services\OrderService;
use Modules\Store\Support\CartCookie;
use Modules\Wholesale\Entities\Merchant;

/**
 * Incoming goods with an expected date can be reserved by merchants (pre-orders): a B2B sale may take
 * the stock below 0, down to minus what is incoming. The storefront never sells them.
 */
class PreorderTest extends WholesaleTestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant();
    }

    public function test_b2c_never_sells_incoming_goods(): void
    {
        $belt = $this->belt(stock: 0, incoming: 10);

        try {
            app(InventoryService::class)->sell([$belt->id => 1]);
            $this->fail('A plain sale took incoming goods.');
        } catch (InsufficientStock) {
        }

        $this->post($this->siteUrl('/ar/cart'), ['product_id' => $belt->id, 'quantity' => 1])->assertSessionHasErrors();
        $this->assertSame(0, (int) $belt->fresh()->stock_quantity);
    }

    public function test_after_a_preorder_the_storefront_still_sells_nothing(): void
    {
        $belt = $this->belt(stock: 2, incoming: 10);
        app(InventoryService::class)->sellIncludingIncoming([$belt->id => 5]);

        $this->assertSame(-3, (int) $belt->fresh()->stock_quantity);
        $this->assertSame('out_of_stock', $belt->fresh()->stock_status);

        $this->expectException(InsufficientStock::class);
        app(OrderService::class)->checkout(['payment_method' => 1, 'customer_name' => 'x', 'contact_phone' => '0599', 'delivery_address' => 'x'], [['product_id' => $belt->id, 'quantity' => 1, 'price' => 40]]);
    }

    public function test_the_grid_shows_incoming_on_a_date(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['black-S']->forceFill(['stock_quantity' => 0, 'stock_status' => 'out_of_stock', 'incoming_quantity' => 6, 'incoming_expected_at' => self::day(20)])->save();

        $this->actingAs($this->merchant->user);
        $cells = collect($this->props('/ar/wholesale/product/'.$shirt->slug, 'Wholesale/Portal/Product')['product']['rows'])
            ->flatMap(fn (array $row) => $row['cells'])->keyBy('id');

        $cell = $cells[$variants['black-S']->id];
        $this->assertTrue($cell['orderable']);
        $this->assertSame(6, $cell['available']);
        $this->assertSame(0, $cell['in_stock']);
        $this->assertSame(6, $cell['incoming']['quantity']);
        $this->assertSame(self::day(20), $cell['incoming']['date']);
        $this->assertStringContainsString(self::day(20), $cell['incoming']['label']);
        $this->assertNull($cells[$variants['black-M']->id]['incoming']);
    }

    public function test_a_merchant_can_order_up_to_stock_plus_incoming(): void
    {
        $belt = $this->belt(stock: 3, incoming: 5);

        $this->setCart($belt, 9)->assertSessionHasErrors('quantities');
        $this->setCart($belt, 8)->assertSessionHasNoErrors();

        $line = $this->actingAs($this->merchant->user)->getJson($this->siteUrl('/ar/wholesale/cart'))->assertOk()->json('groups.0.lines.0');
        $this->assertSame(8, $line['available']);
        $this->assertSame(5, $line['preorder']);
        $this->assertNull($line['problem']);
    }

    public function test_undated_incoming_is_not_offered(): void
    {
        $belt = $this->belt(stock: 0, incoming: 5, date: null);

        $this->setCart($belt, 1)->assertSessionHasErrors('quantities');
    }

    public function test_incoming_expected_in_the_past_is_not_offered(): void
    {
        $belt = $this->belt(stock: 0, incoming: 5, date: now()->subDay()->toDateString());

        $this->setCart($belt, 1)->assertSessionHasErrors('quantities');

        $this->expectException(InsufficientStock::class);
        app(InventoryService::class)->sellIncludingIncoming([$belt->id => 1]);
    }

    public function test_checkout_records_the_preorder_part_of_each_line(): void
    {
        $belt = $this->belt(stock: 3, incoming: 5);
        $this->setCart($belt, 5)->assertSessionHasNoErrors();

        $this->checkout()->assertSessionHasNoErrors();

        $item = Order::query()->sole()->items()->sole();
        $this->assertSame(2, (int) $item->preorder_quantity);
        $this->assertSame(-2, (int) $belt->fresh()->stock_quantity);
        $this->assertSame(5, (int) $belt->fresh()->incoming_quantity);
        $this->assertSame(-5, (int) StockMovement::query()->where('product_id', $belt->id)->latest('id')->value('quantity'));
    }

    public function test_a_lowered_line_lowers_its_preorder(): void
    {
        $belt = $this->belt(stock: 3, incoming: 5);
        $this->setCart($belt, 6)->assertSessionHasNoErrors();
        $this->checkout()->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $this->assertSame(3, (int) $order->items()->sole()->preorder_quantity);

        $edit = fn (int $quantity) => app(OrderService::class)->update($order->id, [
            'status' => $order->fresh()->status->value,
            'items' => [['id' => $order->items()->sole()->id, 'product_id' => $belt->id, 'quantity' => $quantity]],
            'delivery_type' => 1, 'delivery_fee' => 0, 'payment_method' => 1, 'payment_status' => 2,
        ], $this->staff([])->id);

        $edit(4); // still more than the stock had: 3 of them were never in stock
        $this->assertSame(3, (int) $order->items()->sole()->preorder_quantity);

        $edit(2);
        $this->assertSame(2, (int) $order->items()->sole()->preorder_quantity);
    }

    public function test_a_line_from_stock_has_no_preorder(): void
    {
        $belt = $this->belt(stock: 10, incoming: 5);
        $this->setCart($belt, 4)->assertSessionHasNoErrors();

        $this->checkout()->assertSessionHasNoErrors();

        $this->assertNull(Order::query()->sole()->items()->sole()->preorder_quantity);
    }

    public function test_a_b2b_sale_stops_at_minus_incoming(): void
    {
        $belt = $this->belt(stock: 3, incoming: 5);

        try {
            app(InventoryService::class)->sellIncludingIncoming([$belt->id => 9]);
            $this->fail('Sold past the incoming goods.');
        } catch (InsufficientStock) {
        }

        $this->assertSame([$belt->id => 5], app(InventoryService::class)->sellIncludingIncoming([$belt->id => 8]));
        $this->assertSame(-5, (int) $belt->fresh()->stock_quantity);
    }

    public function test_receiving_lowers_incoming_floored_at_zero(): void
    {
        $belt = $this->belt(stock: 3, incoming: 5);
        app(InventoryService::class)->sellIncludingIncoming([$belt->id => 5]);

        app(InventoryService::class)->receive($belt, 3, null, null);
        $this->assertSame(1, (int) $belt->fresh()->stock_quantity);
        $this->assertSame(2, (int) $belt->fresh()->incoming_quantity);

        app(InventoryService::class)->receive($belt, 4, null, null);
        $this->assertSame(5, (int) $belt->fresh()->stock_quantity);
        $this->assertSame(0, (int) $belt->fresh()->incoming_quantity);
        $this->assertNull($belt->fresh()->incoming_expected_at);
    }

    public function test_cancelling_a_preorder_restocks(): void
    {
        $belt = $this->belt(stock: 3, incoming: 5);
        $this->setCart($belt, 6)->assertSessionHasNoErrors();
        $this->checkout()->assertSessionHasNoErrors();
        $this->assertSame(-3, (int) $belt->fresh()->stock_quantity);

        app(OrderService::class)->changeStatus(Order::query()->sole(), OrderStatus::CANCELLED, $this->staff([])->id);

        $this->assertSame(3, (int) $belt->fresh()->stock_quantity);
        $this->assertSame(5, (int) $belt->fresh()->incoming_quantity);
    }

    public function test_incoming_cannot_drop_below_what_is_preordered(): void
    {
        $belt = $this->belt(stock: 0, incoming: 5);
        app(InventoryService::class)->sellIncludingIncoming([$belt->id => 2]);

        try {
            app(InventoryService::class)->setIncoming($belt, 1, self::day(20), null);
            $this->fail('Incoming dropped below the pre-orders.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('incoming_quantity', $e->errors());
        }

        app(InventoryService::class)->setIncoming($belt, 2, self::day(39), null);
        $this->assertSame(2, (int) $belt->fresh()->incoming_quantity);
        $this->assertSame(self::day(39), $belt->fresh()->incoming_expected_at->toDateString());
    }

    private function belt(int $stock, int $incoming, ?string $date = 'soon'): Product
    {
        return $this->single([
            'stock_quantity' => $stock,
            'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
            'incoming_quantity' => $incoming,
            'incoming_expected_at' => $date === 'soon' ? self::day(20) : $date,
        ]);
    }

    private function setCart(Product $product, int $quantity): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale'))
            ->put($this->siteUrl('/ar/wholesale/cart/'.$product->id), ['quantities' => [$product->id => $quantity]]);
    }

    private function checkout(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ]);
    }
}
