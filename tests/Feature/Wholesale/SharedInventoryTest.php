<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Exceptions\InsufficientStock;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Services\InventoryService;
use Modules\Items\Services\OrderService;
use Modules\Store\Support\CartCookie;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Repositories\Contracts\WholesaleCartRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Services\WholesaleCartService;
use Modules\Wholesale\Services\WholesalePricing;

/**
 * The storefront and the wholesale portal sell from one stock, through InventoryService: neither
 * can sell what the other already sold.
 */
class SharedInventoryTest extends WholesaleTestCase
{
    public function test_b2c_and_b2b_cannot_oversell_each_other(): void
    {
        $belt = $this->single(['stock_quantity' => 5]);
        $merchant = $this->merchant();
        $city = $this->city('رام الله');
        $this->zone([$city]);

        // The merchant puts all 5 in the wholesale cart (nothing is reserved).
        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$belt->id), ['quantities' => [$belt->id => 5]])->assertSessionHasNoErrors();

        // A buyer orders 3 on the storefront first.
        $this->flushSession();
        auth()->logout();
        $add = $this->post($this->siteUrl('/ar/cart'), ['product_id' => $belt->id, 'quantity' => 3])->assertSessionHasNoErrors();
        $token = (string) $add->getCookie(CartCookie::NAME)?->getValue();
        $this->withCookie(CartCookie::NAME, $token)->post($this->siteUrl('/ar/checkout'), [
            'name' => 'زبون', 'phone' => '0599000123', 'email' => null, 'city_id' => $city->id, 'district_id' => null,
            'address' => 'عنوان', 'notes' => null, 'payment_method' => 'cash', 'coupon' => null, 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $belt->fresh()->stock_quantity);

        // The merchant's 5 no longer fit: refused, nothing taken.
        $this->flushSession();
        $this->actingAs($merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors();
        $this->assertSame(0, Order::query()->where('channel', OrderChannel::B2B->value)->count());
        $this->assertSame(2, (int) $belt->fresh()->stock_quantity);

        // 2 fit; then the storefront cannot sell the last units again.
        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$belt->id), ['quantities' => [$belt->id => 2]])->assertSessionHasNoErrors();
        $this->actingAs($merchant->user)->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $belt->fresh()->stock_quantity);

        $this->expectException(InsufficientStock::class);
        app(OrderService::class)->checkout(['payment_method' => 1, 'customer_name' => 'x', 'contact_phone' => '0599', 'delivery_address' => 'x'], [['product_id' => $belt->id, 'quantity' => 1, 'price' => 40]]);
    }

    public function test_a_sale_between_the_cart_read_and_the_sell_is_caught_under_the_lock(): void
    {
        $belt = $this->single(['stock_quantity' => 5]);
        $merchant = $this->merchant();
        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$belt->id), ['quantities' => [$belt->id => 4]])->assertSessionHasNoErrors();

        // The checkout reads the cart (4 of 5 fit), then a storefront sale takes 3 before it sells.
        $this->app->instance(WholesaleCartService::class, new class(app(WholesaleCartRepositoryInterface::class), app(WholesaleProductRepositoryInterface::class), app(ProductRepositoryInterface::class), app(WholesalePricing::class), $belt->id) extends WholesaleCartService
        {
            private bool $sold = false;

            public function __construct($carts, $wholesaleProducts, $products, $pricing, private readonly int $productId)
            {
                parent::__construct($carts, $wholesaleProducts, $products, $pricing);
            }

            public function read(WholesaleCart $cart, Merchant $merchant): array
            {
                $read = parent::read($cart, $merchant);

                if (! $this->sold) {
                    $this->sold = true;
                    app(InventoryService::class)->sell([$this->productId => 3]);
                }

                return $read;
            }
        });

        $this->actingAs($merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('items');

        // InventoryService refused under the row lock (`items` is InsufficientStock's key): no order.
        // The simulated sale ran inside the checkout's transaction here, so it rolled back with it.
        $this->assertSame(0, Order::query()->where('channel', OrderChannel::B2B->value)->count());
        $this->assertSame(5, (int) $belt->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->where('product_id', $belt->id)->where('quantity', -4)->count());
    }

    public function test_a_cancelled_b2b_order_restocks(): void
    {
        $belt = $this->single(['stock_quantity' => 5]);
        $merchant = $this->merchant();

        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$belt->id), ['quantities' => [$belt->id => 4]]);
        $this->actingAs($merchant->user)->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, (int) $belt->fresh()->stock_quantity);

        app(OrderService::class)->changeStatus(Order::query()->sole(), OrderStatus::CANCELLED, $this->staff([])->id);

        $this->assertSame(5, (int) $belt->fresh()->stock_quantity);
    }
}
