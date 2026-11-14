<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Store\Entities\Address;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Services\WholesaleCheckoutService;
use Modules\Wholesale\Settings\WholesaleSettings;

/**
 * Wholesale checkout: a B2B order awaiting confirmation, at wholesale prices, with free delivery,
 * its stock taken; the minimum order; one order per submit; the merchant's orders.
 */
class WholesaleCheckoutTest extends WholesaleTestCase
{
    private Merchant $merchant;

    private Product $shirt;

    /** @var array<string, Product> */
    private array $variants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant(attributes: ['tier_id' => $this->tier('ذهبي', 10)->id, 'city_id' => $this->city('الخليل')->id]);
        [$this->shirt, $this->variants] = $this->shirt();
        RateLimiter::clear('wholesale-checkout|'.$this->merchant->user_id);
    }

    public function test_checkout_places_a_b2b_order_awaiting_confirmation(): void
    {
        $this->fill(['black-S' => 2, 'white-M' => 3]);

        $this->place()->assertRedirect(route('store.wholesale.checkout.done'))->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->sole();
        $this->assertSame(OrderChannel::B2B, $order->channel);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
        $this->assertSame($this->merchant->user_id, $order->user_id);
        $this->assertSame('محل الأناقة', $order->customer_name);
        $this->assertSame('شارع السوق 5', $order->delivery_address);
        $this->assertSame($this->merchant->city_id, $order->city_id);
        $this->assertSame(PaymentMethod::CASH, $order->payment_method);
        $this->assertNotNull(WholesaleCart::query()->sole()->order_id);

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/checkout/done'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Wholesale/Portal/CheckoutDone')->where('order.number', $order->order_number)->where('order.awaiting_confirmation', true)->etc());
    }

    public function test_unit_prices_are_wholesale_prices_and_delivery_is_free(): void
    {
        QuantityBreak::query()->create(['product_id' => $this->shirt->id, 'min_quantity' => 5, 'discount_percent' => 10]);
        $this->fill(['black-S' => 2, 'white-M' => 3]);

        $this->place()->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->sole();
        // 50 - 10 % (tier) = 45, then - 10 % (5 pieces) = 40.5
        $this->assertSame(['40.50', '40.50'], $order->items->pluck('price')->all());
        $this->assertSame('202.50', $order->sub_total);
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('202.50', $order->total);
        $this->assertNotEmpty($order->items[0]->product_name);
        $this->assertNotEmpty($order->items[0]->options);
    }

    public function test_stock_is_taken(): void
    {
        $this->fill(['black-S' => 2]);

        $this->place()->assertSessionHasNoErrors();

        $this->assertSame(8, (int) $this->variants['black-S']->fresh()->stock_quantity);
    }

    public function test_below_the_minimum_is_refused_with_what_is_missing(): void
    {
        $settings = app(WholesaleSettings::class);
        $settings->minimum_order_value = 1000;
        $settings->save();
        $this->fill(['black-S' => 2]);

        $this->place()->assertSessionHasErrors('minimum');
        $this->assertSame(0, Order::query()->count());
        $this->assertStringContainsString('910.00', session('errors')->first('minimum')); // 1000 - 2 × 45
    }

    public function test_merchant_override_of_the_minimum(): void
    {
        $settings = app(WholesaleSettings::class);
        $settings->minimum_order_pieces = 50;
        $settings->save();
        $this->merchant->update(['minimum_order_pieces' => 2]);
        $this->fill(['black-S' => 2]);

        $this->place()->assertSessionHasNoErrors();
    }

    public function test_a_saved_address_or_the_shop_address(): void
    {
        $address = Address::query()->create(['user_id' => $this->merchant->user_id, 'recipient_name' => 'مخزن', 'phone' => '0599000777', 'city_id' => $this->merchant->city_id, 'address_line' => 'المخزن الرئيسي', 'is_default' => true]);
        $stranger = Address::query()->create(['user_id' => User::forceCreate(['name' => 'x', 'email' => 'x-'.Str::random(6).'@x.test', 'password' => 'x'])->id, 'recipient_name' => 'x', 'phone' => '0599000888', 'city_id' => $this->merchant->city_id, 'address_line' => 'غريب', 'is_default' => true]);

        $this->fill(['black-S' => 1]);
        $this->place(['address_source' => 'saved', 'address_id' => $stranger->id])->assertSessionHasErrors('address_id');
        $this->place(['address_source' => 'saved', 'address_id' => $address->id])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame('المخزن الرئيسي', $order->delivery_address);
        $this->assertSame('0599000777', $order->contact_phone);
    }

    public function test_a_shop_without_an_address_must_choose_a_saved_one(): void
    {
        $this->merchant->update(['shop_address' => null]);
        $this->fill(['black-S' => 1]);

        $this->place()->assertSessionHasErrors('address_source');
    }

    public function test_bank_transfer_terms_and_credit_is_not_offered_yet(): void
    {
        $this->fill(['black-S' => 1]);

        $this->place(['payment_terms' => 'credit'])->assertSessionHasErrors('payment_terms');
        $this->place(['payment_terms' => 'bank_transfer'])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame(PaymentMethod::BANK_TRANSFER, $order->payment_method);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
    }

    public function test_double_submit_places_one_order(): void
    {
        $this->fill(['black-S' => 1]);
        $token = (string) Str::uuid();

        $this->place(['submit_token' => $token])->assertRedirect(route('store.wholesale.checkout.done'));
        $this->place(['submit_token' => $token])->assertRedirect(route('store.wholesale.checkout.done'));
        $this->place(['submit_token' => (string) Str::uuid()])->assertRedirect();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(9, (int) $this->variants['black-S']->fresh()->stock_quantity);
    }

    public function test_a_second_submit_that_got_past_the_token_gets_the_same_order(): void
    {
        $this->fill(['black-S' => 1]);
        // Both requests read the open cart before either placed it (the token was different, or lost).
        $cart = WholesaleCart::query()->sole();
        $service = app(WholesaleCheckoutService::class);
        $data = ['address_source' => 'shop', 'payment_terms' => 'cash_on_delivery'];

        $first = $service->place($cart, $this->merchant, $data);
        $second = $service->place($cart, $this->merchant, $data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(9, (int) $this->variants['black-S']->fresh()->stock_quantity);
    }

    public function test_checkout_refuses_a_broken_size_run(): void
    {
        $this->shirt->forceFill(['wholesale_by_piece' => false])->save();
        $this->fill(['black-S' => 2, 'black-M' => 2, 'black-L' => 2]);
        // The cart was changed outside its own checks (an older cart, or a size restocked since).
        WholesaleCart::query()->sole()->items()->where('product_id', $this->variants['black-M']->id)->update(['quantity' => 1]);

        $this->place()->assertSessionHasErrors('groups.'.$this->shirt->id);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_is_rate_limited(): void
    {
        foreach (range(1, 6) as $ignored) {
            $this->place(['payment_terms' => 'nope']);
        }

        $this->place()->assertSessionHasErrors('checkout');
    }

    public function test_the_checkout_page_sends_back_to_the_cart_when_it_cannot_be_ordered(): void
    {
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/checkout'))->assertRedirect(route('store.wholesale.cart'));

        $this->fill(['black-S' => 1]);
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/checkout'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Wholesale/Portal/Checkout')->has('paymentTerms', 2)->where('shop.address', 'شارع السوق 5')->etc());
    }

    public function test_the_admin_list_shows_b2b_with_the_shop_name(): void
    {
        $this->fill(['black-S' => 1]);
        $this->place();

        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');

        $this->actingAs($admin)->get($this->siteUrl('/ar/admin/orders?filter[channel]=b2b'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)
            ->where('table.data.0.channel', 'b2b')
            ->where('table.data.0.customer', 'محل الأناقة')
            ->etc());
    }

    public function test_the_merchant_sees_their_wholesale_orders_only(): void
    {
        $this->fill(['black-S' => 1]);
        $this->place();
        $order = Order::query()->sole();

        $other = $this->merchant();
        $theirs = Order::query()->create(['order_number' => 'B2B-OTHER', 'user_id' => $other->user_id, 'channel' => OrderChannel::B2B, 'status' => OrderStatus::PENDING, 'sub_total' => 0, 'discount' => 0, 'delivery_fee' => 0, 'total' => 0]);
        Order::query()->create(['order_number' => 'B2C-MINE', 'user_id' => $this->merchant->user_id, 'channel' => OrderChannel::B2C, 'status' => OrderStatus::PENDING, 'sub_total' => 0, 'discount' => 0, 'delivery_fee' => 0, 'total' => 0]);

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/orders'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Portal/Orders')
            ->where('orders.total', 1)
            ->where('orders.data.0.number', $order->order_number)
            ->etc());

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/orders/'.$order->order_number))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Wholesale/Portal/OrderDetail')->where('order.number', $order->order_number)->missing('order.internal_notes')->etc());
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/orders/'.$theirs->order_number))->assertNotFound();
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/orders/B2C-MINE'))->assertNotFound();
    }

    /**
     * @param  array<string, int>  $quantities
     */
    private function fill(array $quantities): void
    {
        $byId = [];
        foreach ($quantities as $key => $quantity) {
            $byId[$this->variants[$key]->id] = $quantity;
        }

        $this->actingAs($this->merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id), ['quantities' => $byId])->assertSessionHasNoErrors();
    }

    private function place(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop',
            'address_id' => null,
            'notes' => 'يرجى الاتصال قبل التوصيل',
            'payment_terms' => 'cash_on_delivery',
            'submit_token' => (string) Str::uuid(),
            ...$overrides,
        ]);
    }
}
