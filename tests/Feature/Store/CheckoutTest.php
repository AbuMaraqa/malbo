<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Enums\ProductStatus;
use Modules\Store\Contracts\PaymentGateway;
use Modules\Store\Entities\Cart;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Guest checkout with cash on delivery: the server prices everything, the order waits for MALBO to
 * confirm it, the stock is taken and the staff are told.
 */
class CheckoutTest extends StorefrontTestCase
{
    use MakesShippingZones;

    private Product $product;

    private City $city;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create(['name' => 'قميص', 'base_price' => 100, 'manage_stock' => true, 'stock_quantity' => 5]);
        $this->city = $this->city('رام الله', ['المصيون']);
        $this->zone([$this->city], ['name' => 'الوسط', 'fee' => 20, 'eta_min_days' => 1, 'eta_max_days' => 2]);
        $this->token = $this->addToCart($this->product, 2);
        $this->withCookie(CartCookie::NAME, $this->token);
    }

    public function test_an_empty_cart_goes_back_to_the_cart(): void
    {
        $this->withCookie(CartCookie::NAME, 'x'.Str::random(47))->get($this->siteUrl('/ar/checkout'))->assertRedirect(route('store.cart'));
    }

    public function test_the_page_offers_only_deliverable_cities(): void
    {
        $this->city('بلا منطقة');

        $props = $this->props('/ar/checkout', 'Store/Storefront/Checkout');

        $this->assertSame([$this->city->id], array_column($props['cities'], 'value'));
        $this->assertSame(['المصيون'], array_column($props['cities'][0]['districts'], 'label'));
        $this->assertSame([['value' => 'cash', 'label' => 'الدفع عند الاستلام']], $props['paymentMethods']);
        $this->assertTrue(Str::isUuid($props['submitToken']));
        $this->assertSame('200.00 ₪', $props['quote']['subtotal_label']);
        $this->assertSame(1, count($props['lines']));
    }

    public function test_the_quote_follows_the_city_and_coupon(): void
    {
        $this->coupon('TEN', OfferType::PERCENTAGE, 10);

        $quote = $this->props('/ar/checkout?city_id='.$this->city->id.'&coupon=ten', 'Store/Storefront/Checkout')['quote'];

        $this->assertEquals([200, 20, 20, 200], [$quote['subtotal'], $quote['discount'], $quote['delivery_fee'], $quote['total']]);
        $this->assertSame('1–2 أيام', $quote['zone']['eta']);
        $this->assertTrue($quote['coupon']['valid']);
    }

    public function test_placing_a_cash_order(): void
    {
        Notification::fake();
        $district = $this->city->districts()->first();

        $this->place(['district_id' => $district->id])->assertRedirect(route('store.checkout.done'))->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->sole();
        $this->assertSame(OrderChannel::B2C, $order->channel);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
        $this->assertSame(PaymentStatus::UNPAID, $order->payment_status);
        $this->assertSame(PaymentMethod::CASH, $order->payment_method);
        $this->assertSame(['Guest Buyer', 'buyer@example.com', '0599123456', 'شارع الإرسال 5'], [$order->customer_name, $order->customer_email, $order->contact_phone, $order->delivery_address]);
        $this->assertSame([$this->city->id, $district->id], [$order->city_id, $order->district_id]);
        $this->assertSame(['200.00', '0.00', '20.00', '220.00'], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total]);
        $this->assertNull($order->user_id);
        $this->assertNull($order->branch_id);
        $this->assertSame(3, $this->product->fresh()->stock_quantity);

        $cart = Cart::query()->sole();
        $this->assertSame($order->id, $cart->order_id);
        $this->assertNotNull($cart->converted_at);

        $done = $this->props('/ar/checkout/done', 'Store/Storefront/CheckoutDone');
        $this->assertSame($order->order_number, $done['order']['number']);
        $this->assertSame(route('store.track', ['number' => $order->order_number]), $done['order']['track_url']);
        $this->assertSame(0, $this->props('/ar', 'Store/Storefront/Home')['store']['cart_count']);
    }

    public function test_totals_from_the_browser_are_ignored(): void
    {
        $this->coupon('FIVE', OfferType::FIXED, 5);

        $this->place(['coupon' => 'five', 'total' => 1, 'sub_total' => 1, 'discount' => 199, 'delivery_fee' => 0, 'price' => 1])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame(['200.00', '5.00', '20.00', '215.00'], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total]);
        $this->assertSame('FIVE', $order->coupon_code);
        $this->assertSame('100.00', $order->items()->sole()->price);
    }

    public function test_a_city_without_a_zone_is_refused(): void
    {
        $this->place(['city_id' => $this->city('بعيدة')->id, 'district_id' => null])->assertSessionHasErrors('city_id');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_the_district_must_belong_to_the_city(): void
    {
        $other = $this->city('أخرى', ['حي آخر']);
        $this->zone([$other]);

        $this->place(['district_id' => $other->districts()->first()->id])->assertSessionHasErrors('district_id');
        $this->place(['district_id' => null])->assertSessionHasErrors('district_id'); // the city has districts
        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_shortage_keeps_the_cart_and_names_each_line(): void
    {
        $other = Product::factory()->create(['name' => 'بنطال', 'manage_stock' => true, 'stock_quantity' => 3]);
        $this->addToCart($other, 2, $this->token);
        $this->product->update(['stock_quantity' => 1]);
        $other->update(['stock_quantity' => 0]);

        $response = $this->place();

        $response->assertSessionHasErrors();
        $errors = session('errors')->getBag('default')->toArray();
        $this->assertCount(2, array_filter(array_keys($errors), fn (string $key) => str_starts_with($key, 'items.')));
        $this->assertSame(0, Order::query()->count());
        $this->assertNull(Cart::query()->sole()->order_id);
        $this->assertSame(1, $this->product->fresh()->stock_quantity);
    }

    public function test_double_submit_places_one_order(): void
    {
        $token = (string) Str::uuid();

        $this->place(['submit_token' => $token])->assertRedirect(route('store.checkout.done'));
        $this->place(['submit_token' => $token])->assertRedirect(route('store.checkout.done'));
        $this->place()->assertRedirect(route('store.checkout.done'));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
    }

    public function test_the_confirmation_is_only_for_this_session(): void
    {
        $this->get($this->siteUrl('/ar/checkout/done'))->assertRedirect(route('store.track'));
    }

    public function test_staff_are_told(): void
    {
        Notification::fake();
        $clerk = User::forceCreate(['name' => 'Clerk', 'email' => 'clerk@store.test', 'password' => 'secret-password'])->givePermissionTo('Order View List');

        $this->place()->assertSessionHasNoErrors();

        Notification::assertSentTo($clerk, GeneralNotification::class);
    }

    public function test_checkout_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->place(['name' => '']);
        }

        $this->place()->assertStatus(429);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_online_payment_is_hidden_until_a_gateway_is_bound(): void
    {
        $this->place(['payment_method' => 'online'])->assertSessionHasErrors('payment_method');

        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function label(): string
            {
                return 'Card';
            }

            public function start(Order $order): string
            {
                return 'https://pay.example/'.$order->order_number;
            }
        });

        $this->assertSame(['cash', 'online'], array_column($this->props('/ar/checkout', 'Store/Storefront/Checkout')['paymentMethods'], 'value'));
        $this->place(['payment_method' => 'online'])->assertRedirect('https://pay.example/'.Order::query()->sole()->order_number);
        $this->assertSame(OrderStatus::PENDING, Order::query()->sole()->status);
    }

    public function test_the_phone_is_stored_in_canonical_form(): void
    {
        $this->place(['phone' => '+970 599-123-456'])->assertSessionHasNoErrors();

        $this->assertSame('0599123456', Order::query()->sole()->contact_phone);
    }

    public function test_coupon_checks_are_rate_limited(): void
    {
        $this->coupon('TEN', OfferType::PERCENTAGE, 10);

        for ($i = 1; $i <= 10; $i++) {
            $this->props('/ar/checkout?coupon=GUESS'.$i, 'Store/Storefront/Checkout');
        }

        $quote = $this->props('/ar/checkout?coupon=TEN', 'Store/Storefront/Checkout')['quote'];

        $this->assertFalse($quote['coupon']['valid']);
        $this->assertSame('محاولات كثيرة لرموز القسائم. حاول مرة أخرى بعد دقيقة.', $quote['coupon']['message']);
        $this->assertEquals(0, $quote['discount']);
        // Without a code the page keeps working.
        $this->assertNull($this->props('/ar/checkout', 'Store/Storefront/Checkout')['quote']['coupon']);
    }

    public function test_a_product_unpublished_after_adding_blocks_checkout(): void
    {
        $this->product->update(['status' => ProductStatus::DRAFT]);

        $this->get($this->siteUrl('/ar/checkout'))->assertRedirect(route('store.cart'));
        $this->place()->assertSessionHasErrors();
        $this->assertSame(0, Order::query()->count());
        $this->assertNull(Cart::query()->sole()->order_id);
    }

    public function test_the_per_phone_coupon_limit_is_enforced_when_placing(): void
    {
        $this->coupon('ONCE', OfferType::FIXED, 10);
        Order::query()->create(['order_number' => 'OLD-1', 'sub_total' => 100, 'status' => OrderStatus::DELIVERED, 'contact_phone' => '0599123456', 'coupon_code' => 'ONCE']);

        $this->place(['coupon' => 'ONCE', 'phone' => '+970599123456'])->assertSessionHasErrors('coupon');
        $this->assertSame(1, Order::query()->count());

        $this->place(['coupon' => 'ONCE', 'phone' => '0599765432'])->assertSessionHasNoErrors();
        $this->assertSame('10.00', Order::query()->latest('id')->first()->discount);
    }

    public function test_placing_with_a_free_shipping_coupon(): void
    {
        $this->coupon('SHIPFREE', OfferType::FREE_SHIPPING, 0);

        $this->place(['coupon' => 'shipfree'])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame(['200.00', '0.00', '0.00', '200.00', 'SHIPFREE'], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total, $order->coupon_code]);
    }

    private function place(array $overrides = []): TestResponse
    {
        return $this->withCookie(CartCookie::NAME, $this->token)
            ->from($this->siteUrl('/ar/checkout'))
            ->post($this->siteUrl('/ar/checkout'), [
                'submit_token' => (string) Str::uuid(),
                'name' => 'Guest Buyer',
                'phone' => '0599123456',
                'email' => 'buyer@example.com',
                'city_id' => $this->city->id,
                'district_id' => $this->city->districts()->first()?->id,
                'address' => 'شارع الإرسال 5',
                'notes' => null,
                'coupon' => null,
                'payment_method' => 'cash',
                ...$overrides,
            ]);
    }

    private function addToCart(Product $product, int $quantity, ?string $token = null): string
    {
        $request = $token ? $this->withCookie(CartCookie::NAME, $token) : $this;
        $response = $request->post($this->siteUrl('/ar/cart'), ['product_id' => $product->id, 'quantity' => $quantity])->assertSessionHasNoErrors();

        return $token ?? (string) $response->getCookie(CartCookie::NAME)?->getValue();
    }

    private function coupon(string $code, OfferType $type, float $value): Offer
    {
        return Offer::query()->create([
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'is_active' => true,
            'min_purchase_amount' => 0,
            'usage_limit' => 10,
            'usage_limit_per_user' => 1,
            'offerable_type' => $this->product->getMorphClass(),
            'offerable_id' => $this->product->id,
            'created_by' => User::query()->value('id'),
        ]);
    }
}
