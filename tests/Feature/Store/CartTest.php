<?php

namespace Tests\Feature\Store;

use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\database\factories\FactoryUser;
use Modules\Store\Entities\Cart;
use Modules\Store\Services\CartService;
use Modules\Store\Support\CartCookie;

/**
 * The storefront cart: a database row held by a long-lived cookie. Lines are priced live and never
 * reserve stock.
 */
class CartTest extends StorefrontTestCase
{
    private Product $shirt;

    private Product $black;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shirt = Product::factory()->create(['name' => 'قميص', 'slug' => 'shirt', 'base_price' => 100]);
        $this->black = Product::factory()->variationOf($this->shirt)->create([
            'name' => 'قميص أسود',
            'sku' => 'SH-BLK',
            'base_price' => 120,
            'manage_stock' => true,
            'stock_quantity' => 5,
            'max_quantity_per_order' => 4,
        ]);
        $color = Attribute::factory()->create(['name' => 'اللون', 'type' => 3]);
        $this->black->attributeValues()->attach(AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أسود'])->id);
    }

    public function test_adding_creates_the_cart_and_its_cookie(): void
    {
        $response = $this->add($this->black->id, 2);

        $response->assertRedirect()->assertSessionHasNoErrors()->assertCookie(CartCookie::NAME);
        $cart = Cart::query()->with('items')->sole();
        $this->assertSame($this->token($response), $cart->token);
        $this->assertNotNull($cart->uuid);
        $this->assertNotNull($cart->last_activity_at);
        $this->assertSame([[$this->black->id, 2]], $cart->items->map(fn ($item) => [$item->product_id, $item->quantity])->all());
    }

    public function test_the_same_product_is_merged(): void
    {
        $token = $this->token($this->add($this->black->id, 1));

        $this->add($this->black->id, 2, $token)->assertSessionHasNoErrors();

        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(3, Cart::query()->sole()->items()->sole()->quantity);
    }

    public function test_a_quantity_beyond_the_limit_or_stock_is_refused(): void
    {
        $token = $this->token($this->add($this->black->id, 3));

        $this->add($this->black->id, 2, $token)->assertSessionHasErrors('quantity'); // 5 > 4 per order
        $this->assertSame(3, Cart::query()->sole()->items()->sole()->quantity);

        $this->black->update(['max_quantity_per_order' => null, 'stock_quantity' => 3]);
        $this->add($this->black->id, 1, $token)->assertSessionHasErrors('quantity'); // only 3 in stock

        $this->black->update(['stock_quantity' => 0]);
        $this->add($this->black->id, 1)->assertSessionHasErrors('product_id');
    }

    public function test_only_sellable_products_can_be_added(): void
    {
        $this->add($this->shirt->id, 1)->assertSessionHasErrors('product_id'); // it has variants: one of them is sold

        $draft = Product::factory()->draft()->create();
        $this->add($draft->id, 1)->assertSessionHasErrors('product_id');

        $withAddons = Product::factory()->create(['manage_stock' => false]);
        $withAddons->addons()->attach(Addon::factory()->create()->id, ['created_by' => FactoryUser::id()]);
        $this->add($withAddons->id, 1)->assertSessionHasErrors('product_id');

        $this->add(999999, 1)->assertSessionHasErrors('product_id');
        $this->add($this->black->id, 0)->assertSessionHasErrors('quantity');

        $this->assertSame(0, Cart::query()->count());
        $this->add(Product::factory()->create(['manage_stock' => false])->id, 1)->assertSessionHasNoErrors();
    }

    public function test_the_cart_page_prices_lines_live(): void
    {
        $token = $this->token($this->add($this->black->id, 2));
        $this->black->update(['sale_price' => 90]);

        $props = $this->withCookie(CartCookie::NAME, $token)->props('/ar/cart', 'Store/Storefront/Cart');

        $line = $props['lines'][0];
        $this->assertSame('قميص', $line['name']);
        $this->assertSame([['name' => 'اللون', 'value' => 'أسود']], $line['options']);
        $this->assertSame('90.00 ₪', $line['price']['label']);
        $this->assertSame('180.00 ₪', $line['total_label']);
        $this->assertSame(2, $line['quantity']);
        $this->assertSame(4, $line['max_quantity']);
        $this->assertTrue($line['available']);
        $this->assertSame(route('store.product', ['slug' => 'shirt']), $line['url']);
        $this->assertSame('180.00 ₪', $props['summary']['subtotal_label']);
        $this->assertTrue($props['summary']['can_checkout']);
    }

    public function test_a_line_whose_product_is_gone_is_flagged_and_left_out(): void
    {
        $other = Product::factory()->create(['base_price' => 50, 'manage_stock' => false]);
        $token = $this->token($this->add($this->black->id, 1));
        $this->add($other->id, 1, $token);

        $this->shirt->update(['status' => ProductStatus::DRAFT]);
        $other->delete();

        $props = $this->withCookie(CartCookie::NAME, $token)->props('/ar/cart', 'Store/Storefront/Cart');

        $this->assertSame([false, false], array_column($props['lines'], 'available'));
        $this->assertNotNull($props['lines'][0]['problem']);
        $this->assertSame('0.00 ₪', $props['summary']['subtotal_label']);
        $this->assertFalse($props['summary']['can_checkout']);
    }

    public function test_update_and_remove_only_touch_this_cart(): void
    {
        $mine = $this->token($this->add($this->black->id, 1));
        $theirs = $this->token($this->add($this->black->id, 1));
        $theirItem = Cart::query()->where('token', $theirs)->sole()->items()->sole();
        $myItem = Cart::query()->where('token', $mine)->sole()->items()->sole();

        $this->withCookie(CartCookie::NAME, $mine)->put($this->siteUrl('/ar/cart/'.$theirItem->id), ['quantity' => 3])->assertNotFound();
        $this->withCookie(CartCookie::NAME, $mine)->delete($this->siteUrl('/ar/cart/'.$theirItem->id))->assertNotFound();

        $this->withCookie(CartCookie::NAME, $mine)->put($this->siteUrl('/ar/cart/'.$myItem->id), ['quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, $myItem->fresh()->quantity);
        $this->withCookie(CartCookie::NAME, $mine)->put($this->siteUrl('/ar/cart/'.$myItem->id), ['quantity' => 9])->assertSessionHasErrors('quantity');

        $this->withCookie(CartCookie::NAME, $mine)->delete($this->siteUrl('/ar/cart/'.$myItem->id))->assertSessionHasNoErrors();
        $this->assertNull($myItem->fresh());
        $this->assertSame(1, $theirItem->fresh()->quantity);
    }

    public function test_the_layout_counts_the_cart(): void
    {
        $this->assertSame(0, $this->props('/ar', 'Store/Storefront/Home')['store']['cart_count']);

        $token = $this->token($this->add($this->black->id, 2));
        $this->add(Product::factory()->create(['manage_stock' => false])->id, 1, $token);

        $store = $this->withCookie(CartCookie::NAME, $token)->props('/ar/shop', 'Store/Storefront/Shop')['store'];
        $this->assertSame(3, $store['cart_count']);
        $this->assertSame(route('store.cart'), $store['cart_url']);
    }

    public function test_the_product_page_says_whether_it_can_go_in_the_cart(): void
    {
        $this->assertTrue($this->props('/ar/product/shirt', 'Store/Storefront/ProductPage')['canAddToCart']);

        $this->shirt->addons()->attach(Addon::factory()->create()->id, ['created_by' => FactoryUser::id()]);
        $this->assertFalse($this->props('/ar/product/shirt', 'Store/Storefront/ProductPage')['canAddToCart']);
    }

    public function test_a_variant_of_an_unpublished_parent_is_refused(): void
    {
        $this->shirt->update(['status' => ProductStatus::DRAFT]);

        $this->add($this->black->id, 1)->assertSessionHasErrors('product_id');
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_changing_a_converted_cart_is_refused(): void
    {
        $this->add($this->black->id, 1);
        $cart = Cart::query()->with('items')->sole();
        $item = $cart->items->sole();

        // Another request placed it as an order after this one read it.
        Cart::query()->whereKey($cart->id)->update(['order_id' => $this->order()->id]);

        foreach ([fn () => app(CartService::class)->setQuantity($cart, $item, 2), fn () => app(CartService::class)->remove($cart, $item)] as $change) {
            try {
                $change();
                $this->fail('A cart that became an order must not change.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('quantity', $e->errors());
            }
        }

        $this->assertSame(1, $item->fresh()->quantity);
    }

    private function order(): Order
    {
        return Order::query()->create(['order_number' => 'CART-1', 'sub_total' => 120, 'contact_phone' => '0599123456']);
    }

    private function add(int $productId, int $quantity, ?string $token = null): TestResponse
    {
        $request = $token ? $this->withCookie(CartCookie::NAME, $token) : $this;

        return $request->from($this->siteUrl('/ar/product/shirt'))
            ->post($this->siteUrl('/ar/cart'), ['product_id' => $productId, 'quantity' => $quantity]);
    }

    private function token(TestResponse $response): string
    {
        return (string) $response->getCookie(CartCookie::NAME)?->getValue();
    }
}
