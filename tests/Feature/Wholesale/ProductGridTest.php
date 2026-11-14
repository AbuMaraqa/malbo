<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Items\Entities\Order;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Entities\WholesaleCart;

/**
 * The portal's product page: a row per color, a cell per size, with live available quantities, the
 * tier's unit price and what is already in the cart.
 */
class ProductGridTest extends WholesaleTestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant(attributes: ['tier_id' => $this->tier('ذهبي', 10)->id]);
    }

    public function test_rows_per_color_and_cells_per_size(): void
    {
        [$shirt, $variants] = $this->shirt();
        TierPrice::query()->create(['product_id' => $variants['white-M']->id, 'tier_id' => $this->merchant->tier_id, 'price' => 40]);
        QuantityBreak::query()->create(['product_id' => $shirt->id, 'min_quantity' => 12, 'discount_percent' => 5]);

        $product = $this->page($shirt->slug);

        $this->assertSame(['S', 'M', 'L'], $product['sizes']);
        $this->assertSame(['أسود', 'أبيض'], array_column($product['rows'], 'name'));
        $this->assertSame('#000000', $product['rows'][0]['swatch']);
        $this->assertSame([$variants['black-S']->id, $variants['black-M']->id, $variants['black-L']->id], array_column($product['rows'][0]['cells'], 'id'));
        $this->assertEquals(45, $product['rows'][0]['cells'][0]['unit_price']);
        $this->assertEquals(40, $product['rows'][1]['cells'][1]['unit_price']);
        $this->assertSame('100.00 ₪', $product['rows'][0]['cells'][0]['retail_label']);
        $this->assertTrue($product['by_piece']);
        $this->assertSame(12, $product['breaks'][0]['min_quantity']);
        $this->assertTrue($product['price_from']);
    }

    public function test_available_quantities_are_live(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['black-M']->update(['stock_quantity' => 3]);
        $variants['black-L']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $variants['white-S']->update(['manage_stock' => false]);

        $cells = collect($this->page($shirt->slug)['rows'])->flatMap(fn (array $row) => $row['cells'])->keyBy('id');

        $this->assertSame(3, $cells[$variants['black-M']->id]['available']);
        $this->assertSame(0, $cells[$variants['black-L']->id]['available']);
        $this->assertFalse($cells[$variants['black-L']->id]['orderable']);
        $this->assertNull($cells[$variants['white-S']->id]['available']);

        $variants['black-M']->update(['stock_quantity' => 1]);
        $cells = collect($this->page($shirt->slug)['rows'])->flatMap(fn (array $row) => $row['cells'])->keyBy('id');
        $this->assertSame(1, $cells[$variants['black-M']->id]['available']);
    }

    public function test_cart_quantities_fill_the_grid(): void
    {
        [$shirt, $variants] = $this->shirt();

        $this->actingAs($this->merchant->user)
            ->put($this->siteUrl('/ar/wholesale/cart/'.$shirt->id), ['quantities' => [$variants['white-L']->id => 4]])
            ->assertSessionHasNoErrors();

        $cells = collect($this->page($shirt->slug)['rows'])->flatMap(fn (array $row) => $row['cells'])->keyBy('id');
        $this->assertSame(4, $cells[$variants['white-L']->id]['in_cart']);
    }

    public function test_a_product_without_variants_has_one_cell(): void
    {
        $belt = $this->single();

        $product = $this->page($belt->slug);

        $this->assertSame([null], $product['sizes']);
        $this->assertSame($belt->id, $product['rows'][0]['cells'][0]['id']);
        $this->assertEquals(18, $product['rows'][0]['cells'][0]['unit_price']);
    }

    public function test_a_product_not_sold_wholesale_is_not_found(): void
    {
        $belt = $this->single(['wholesale_price' => null]);

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/product/'.$belt->slug))->assertNotFound();
    }

    public function test_a_parent_whose_variants_are_all_inactive_is_not_sold(): void
    {
        [$shirt, $variants] = $this->shirt();
        foreach ($variants as $variant) {
            $variant->update(['is_active' => 0]);
        }
        $shirt->update(['manage_stock' => false]);

        // Not as a product without variants: the parent itself never becomes an item.
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/product/'.$shirt->slug))->assertNotFound();
        $this->actingAs($this->merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$shirt->id), ['quantities' => [$shirt->id => 2]])
            ->assertSessionHasErrors('quantities');
        $this->assertNotContains($shirt->id, array_column($this->props('/ar/wholesale', 'Wholesale/Portal/Catalog')['products']['data'], 'id'));
    }

    public function test_a_line_of_such_a_parent_is_refused_at_checkout(): void
    {
        [$shirt, $variants] = $this->shirt();
        $this->actingAs($this->merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$shirt->id), ['quantities' => [$variants['black-S']->id => 2]])
            ->assertSessionHasNoErrors();
        foreach ($variants as $variant) {
            $variant->update(['is_active' => 0]);
        }
        // The parent's own stock is not managed: nothing but this rule stops it from selling.
        $shirt->update(['manage_stock' => false]);
        // Only a line on the parent itself, as an older cart could hold.
        $cart = WholesaleCart::query()->sole();
        $cart->items()->delete();
        $cart->items()->create(['product_id' => $shirt->id, 'quantity' => 2]);

        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors();

        $this->assertSame(0, Order::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $slug): array
    {
        $this->actingAs($this->merchant->user);

        return $this->props('/ar/wholesale/product/'.$slug, 'Wholesale/Portal/Product')['product'];
    }
}
