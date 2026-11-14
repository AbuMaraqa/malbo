<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Cart;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Settings\WholesaleSettings;

/**
 * The wholesale cart: the merchant's own, written a product at a time and checked on the server
 * (sizes of that product, stock, size runs), priced live with quantity breaks.
 */
class WholesaleCartTest extends WholesaleTestCase
{
    private Merchant $merchant;

    private Product $shirt;

    /** @var array<string, Product> */
    private array $variants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant();
        [$this->shirt, $this->variants] = $this->shirt();
    }

    public function test_set_creates_the_cart_for_the_merchant(): void
    {
        $this->set($this->shirt, ['black-S' => 2, 'white-M' => 3])->assertSessionHasNoErrors();

        $cart = WholesaleCart::query()->with('items')->sole();
        $this->assertSame($this->merchant->user_id, $cart->user_id);
        $this->assertNull($cart->order_id);
        $this->assertEqualsCanonicalizing([[$this->variants['black-S']->id, 2], [$this->variants['white-M']->id, 3]], $cart->items->map(fn ($item) => [$item->product_id, $item->quantity])->all());
    }

    public function test_quantities_are_replaced_per_product(): void
    {
        $belt = $this->single();
        $this->set($this->shirt, ['black-S' => 2, 'white-M' => 3]);
        $this->set($belt, [$belt->id => 5]);

        $this->set($this->shirt, ['black-L' => 1, 'white-M' => 0])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [[$this->variants['black-L']->id, 1], [$belt->id, 5]],
            WholesaleCart::query()->sole()->items->map(fn ($item) => [$item->product_id, $item->quantity])->all(),
        );
    }

    public function test_remove_takes_out_every_size(): void
    {
        $this->set($this->shirt, ['black-S' => 2, 'white-M' => 3]);

        $this->actingAs($this->merchant->user)->delete($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id))->assertRedirect();

        $this->assertSame(0, WholesaleCart::query()->sole()->items()->count());
    }

    public function test_more_than_the_stock_is_refused(): void
    {
        $this->variants['black-S']->update(['stock_quantity' => 4]);

        $this->set($this->shirt, ['black-S' => 5])->assertSessionHasErrors('quantities');
        $this->set($this->shirt, ['black-S' => 4])->assertSessionHasNoErrors();

        $this->variants['black-M']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->set($this->shirt, ['black-M' => 1])->assertSessionHasErrors('quantities');
    }

    public function test_an_item_of_another_product_is_refused(): void
    {
        $belt = $this->single();

        $this->set($this->shirt, [$belt->id => 1])->assertSessionHasErrors('quantities');
        $this->set($belt, ['black-S' => 1])->assertSessionHasErrors('quantities');
        $this->set($this->variants['black-S'], [$this->variants['black-S']->id => 1])->assertSessionHasErrors('quantities');
        $this->assertSame(0, (int) WholesaleCart::query()->first()?->items()->count());
    }

    public function test_a_product_not_sold_wholesale_is_refused(): void
    {
        $belt = $this->single(['wholesale_price' => null]);

        $this->set($belt, [$belt->id => 1])->assertSessionHasErrors('quantities');
    }

    public function test_by_the_piece_takes_any_quantities(): void
    {
        $this->set($this->shirt, ['black-S' => 1, 'black-M' => 7])->assertSessionHasNoErrors();
    }

    public function test_size_runs_are_enforced_on_the_server(): void
    {
        $this->shirt->forceFill(['wholesale_by_piece' => false])->save();
        $this->variants['white-L']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);

        // A run of black is S, M and L; of white, S and M (L is out).
        $this->set($this->shirt, ['black-S' => 2, 'black-M' => 2])->assertSessionHasErrors('quantities');
        $this->set($this->shirt, ['black-S' => 2, 'black-M' => 2, 'black-L' => 1])->assertSessionHasErrors('quantities');
        $this->set($this->shirt, ['black-S' => 2, 'black-M' => 2, 'black-L' => 2])->assertSessionHasNoErrors();
        $this->set($this->shirt, ['black-S' => 2, 'black-M' => 2, 'black-L' => 2, 'white-S' => 3, 'white-M' => 3])->assertSessionHasNoErrors();

        $this->assertSame(12, (int) WholesaleCart::query()->sole()->items()->sum('quantity'));

        // A size sold out after it was added breaks the run: the cart says so.
        $this->variants['black-L']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $cart = $this->cart();
        $this->assertNotNull(collect($cart['groups'])->firstWhere('product_id', $this->shirt->id)['problem']);
        $this->assertFalse($cart['summary']['can_checkout']);
    }

    public function test_breaks_are_recomputed_on_the_total_quantity(): void
    {
        QuantityBreak::query()->create(['product_id' => $this->shirt->id, 'min_quantity' => 10, 'discount_percent' => 10]);

        $this->set($this->shirt, ['black-S' => 4, 'white-M' => 5]);
        $group = $this->cart()['groups'][0];
        $this->assertEquals(50, $group['lines'][0]['unit_price']);
        $this->assertEquals(450, $group['total']);

        $this->set($this->shirt, ['black-S' => 5, 'white-M' => 5]);
        $cart = $this->cart();
        $this->assertEquals(45, $cart['groups'][0]['lines'][0]['unit_price']);
        $this->assertEquals(450, $cart['summary']['subtotal']);
        $this->assertSame(10, $cart['summary']['pieces']);
        $this->assertNotNull($cart['groups'][0]['break_label']);
    }

    public function test_the_b2c_cart_is_untouched(): void
    {
        $this->set($this->shirt, ['black-S' => 2]);

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/cart'))->viewData('page')['props']['store']['cart_count'] ?? 0);
    }

    public function test_a_line_or_cart_beyond_what_an_order_can_hold_is_refused(): void
    {
        $belt = $this->single(['wholesale_price' => 9999999, 'manage_stock' => false]);

        // 20 × 9 999 999 = 199 999 980: more than an order total holds (99 999 999.99).
        $this->set($belt, [$belt->id => 20])->assertSessionHasErrors('quantities');
        $this->set($belt, [$belt->id => 9])->assertSessionHasNoErrors();

        // Two lines that fit alone but not together.
        $other = $this->single(['wholesale_price' => 9999999, 'manage_stock' => false]);
        $this->set($other, [$other->id => 2])->assertSessionHasErrors('quantities');
    }

    public function test_checkout_refuses_an_order_too_large_with_a_message(): void
    {
        $belt = $this->single(['wholesale_price' => 9999999, 'manage_stock' => false]);
        $this->set($belt, [$belt->id => 1])->assertSessionHasNoErrors();
        // Grown past the limit outside the cart's own checks (an older cart).
        WholesaleCart::query()->sole()->items()->update(['quantity' => 50]);

        $this->assertFalse($this->cart()['summary']['can_checkout']);

        $response = $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ]);

        $response->assertRedirect()->assertSessionHasErrors();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_converted_cart_whose_order_is_gone_is_never_open_again(): void
    {
        $this->set($this->shirt, ['black-S' => 2]);
        $old = WholesaleCart::query()->sole();
        // Converted, then its order deleted for good: `order_id` falls back to null (nullOnDelete).
        $old->forceFill(['order_id' => null, 'converted_at' => now()])->save();

        $this->set($this->shirt, ['white-M' => 1])->assertSessionHasNoErrors();

        $open = WholesaleCart::query()->whereNull('converted_at')->sole();
        $this->assertNotSame($old->id, $open->id);
        $this->assertSame([[$this->variants['white-M']->id, 1]], $open->items->map(fn ($item) => [$item->product_id, $item->quantity])->all());
        $this->assertSame(1, $old->items()->count());
    }

    public function test_the_minimum_order_is_reported(): void
    {
        $settings = app(WholesaleSettings::class);
        $settings->minimum_order_value = 500;
        $settings->minimum_order_pieces = 12;
        $settings->save();

        $this->set($this->shirt, ['black-S' => 4]);
        $summary = $this->cart()['summary'];

        $this->assertFalse($summary['can_checkout']);
        $this->assertCount(2, $summary['minimum_missing']);
        $this->assertStringContainsString('300.00', $summary['minimum_missing'][0]); // 500 - 4 × 50
        $this->assertStringContainsString('8', $summary['minimum_missing'][1]);

        $this->merchant->update(['minimum_order_value' => 100, 'minimum_order_pieces' => 4]);
        $this->assertTrue($this->cart()['summary']['can_checkout']);
    }

    /**
     * @param  array<string, int>  $quantities  "black-S" or an id => pieces
     */
    private function set(Product $product, array $quantities): TestResponse
    {
        $byId = [];
        foreach ($quantities as $key => $quantity) {
            $byId[is_int($key) ? $key : $this->variants[$key]->id] = $quantity;
        }

        return $this->actingAs($this->merchant->user)
            ->from($this->siteUrl('/ar/wholesale'))
            ->put($this->siteUrl('/ar/wholesale/cart/'.$product->id), ['quantities' => $byId]);
    }

    /**
     * @return array{groups: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function cart(): array
    {
        return $this->actingAs($this->merchant->user)->getJson($this->siteUrl('/ar/wholesale/cart'))->assertOk()->json();
    }
}
