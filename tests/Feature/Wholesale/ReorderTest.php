<?php

namespace Tests\Feature\Wholesale;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Services\OrderService;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Services\WholesaleCartService;

/**
 * Reorder: a past wholesale order's lines go back into the wholesale cart at today's prices and
 * availability; what can no longer be ordered is skipped and listed. The merchant checks the cart.
 */
class ReorderTest extends WholesaleTestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant();
    }

    public function test_reorder_copies_lines_at_current_prices(): void
    {
        $belt = $this->single(['stock_quantity' => 30]);
        [, $variants] = $this->shirt();
        $order = $this->order([[$belt, 3], [$variants['black-S'], 2], [$variants['white-M'], 1]]);
        $belt->update(['wholesale_price' => 25]);

        $this->reorder($order)->assertRedirect(route('store.wholesale.cart'))->assertSessionHas('wholesale_reorder.added', 3);

        $this->assertSame([$belt->id => 3, $variants['black-S']->id => 2, $variants['white-M']->id => 1], $this->cart());
        $lines = collect($this->cartJson()['groups'])->flatMap(fn (array $group) => $group['lines'])->keyBy('product_id');
        $this->assertEquals(25, $lines[$belt->id]['unit_price']);
        $this->assertEquals(50, $lines[$variants['black-S']->id]['unit_price']);
        $this->assertSame(0, Order::query()->where('id', '!=', $order->id)->count(), 'Reorder only fills the cart.');
    }

    public function test_existing_cart_lines_merge_within_the_limit(): void
    {
        // 17 in stock; the order below takes 5: 12 can be ordered.
        $belt = $this->single(['stock_quantity' => 17]);
        app(WholesaleCartService::class)->set($this->merchant, $belt->id, [$belt->id => 4]);
        $order = $this->order([[$belt, 5]]);

        $this->reorder($order)->assertSessionHas('wholesale_reorder.skipped', []);
        $this->assertSame([$belt->id => 9], $this->cart());

        $this->reorder($order);
        $this->assertSame([$belt->id => 12], $this->cart());
        $this->assertStringContainsString('12', session('wholesale_reorder.skipped')[0]);
    }

    public function test_unavailable_and_not_wholesale_lines_are_skipped_with_a_message(): void
    {
        $belt = $this->single(['stock_quantity' => 10]);
        $cap = $this->single(['name' => 'قبعة', 'stock_quantity' => 10]);
        $gone = $this->single(['name' => 'وشاح', 'stock_quantity' => 10]);
        [, $variants] = $this->shirt();
        $order = $this->order([[$belt, 2], [$cap, 2], [$gone, 1], [$variants['black-S'], 1]]);

        $belt->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $cap->update(['wholesale_price' => null]);
        $gone->delete();

        $this->reorder($order)->assertSessionHas('wholesale_reorder.added', 1);

        $this->assertSame([$variants['black-S']->id => 1], $this->cart());
        $skipped = implode("\n", session('wholesale_reorder.skipped'));
        $this->assertStringContainsString('حزام', $skipped);
        $this->assertStringContainsString('قبعة', $skipped);
        $this->assertStringContainsString('وشاح', $skipped);
        $this->assertCount(3, session('wholesale_reorder.skipped'));
    }

    public function test_a_broken_size_run_is_skipped(): void
    {
        [$shirt, $variants] = $this->shirt();
        $shirt->forceFill(['wholesale_by_piece' => false])->save();
        // Placed when white M and L were sold out: one white S was a whole run then.
        $order = $this->order([[$variants['black-S'], 2], [$variants['black-M'], 2], [$variants['black-L'], 2], [$variants['white-S'], 1]]);

        $this->reorder($order)->assertSessionHas('wholesale_reorder.added', 3);

        $this->assertSame([$variants['black-S']->id => 2, $variants['black-M']->id => 2, $variants['black-L']->id => 2], $this->cart());
        $this->assertCount(1, session('wholesale_reorder.skipped'));
        $this->assertStringContainsString('أبيض', session('wholesale_reorder.skipped')[0]);
    }

    public function test_another_merchants_order_cannot_be_reordered(): void
    {
        $belt = $this->single();
        $theirs = $this->order([[$belt, 2]], $this->merchant());

        $this->reorder($theirs)->assertNotFound();
        $this->assertSame(0, WholesaleCart::query()->count());
    }

    public function test_the_order_page_links_to_reorder_and_the_cart_lists_what_was_skipped(): void
    {
        $belt = $this->single(['stock_quantity' => 10]);
        $cap = $this->single(['name' => 'قبعة', 'wholesale_price' => null]);
        $order = $this->order([[$belt, 2], [$cap, 1]]);

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/orders/'.$order->order_number))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.reorder_url', route('store.wholesale.orders.reorder', ['number' => $order->order_number]))->etc());

        $this->reorder($order);

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/cart'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Portal/Cart')
            ->where('reorder.added', 1)
            ->has('reorder.skipped', 1)
            ->etc());
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function order(array $lines, ?Merchant $merchant = null): Order
    {
        $merchant ??= $this->merchant;

        return app(OrderService::class)->checkout([
            'user_id' => $merchant->user_id, 'customer_name' => $merchant->shop_name, 'contact_phone' => $merchant->phone,
            'delivery_address' => 'x', 'payment_method' => 1, 'delivery_fee' => 0, 'discount' => 0,
        ], array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1], 'price' => 1], $lines), OrderChannel::B2B);
    }

    private function reorder(Order $order): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/orders/'.$order->order_number))
            ->post($this->siteUrl('/ar/wholesale/orders/'.$order->order_number.'/reorder'));
    }

    /**
     * @return array<int, int>
     */
    private function cart(): array
    {
        $quantities = WholesaleCartService::quantities(app(WholesaleCartService::class)->current((int) $this->merchant->user_id));
        ksort($quantities);

        return $quantities;
    }

    /**
     * @return array<string, mixed>
     */
    private function cartJson(): array
    {
        return $this->actingAs($this->merchant->user)->getJson($this->siteUrl('/ar/wholesale/cart'))->assertOk()->json();
    }
}
