<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Entities\Address;
use Modules\Store\Services\AddressService;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Checkout for a signed-in buyer: the form starts from the account, the order is the account's,
 * guest orders with the same phone join it, and coupons count the account's orders.
 */
class CheckoutSignedInTest extends StorefrontTestCase
{
    use MakesShippingZones;

    private User $buyer;

    private Product $product;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0599000111', 'password' => 'Secret-pass-123'])
            ->assignRole(UserRole::CUSTOMER->value);
        $this->product = Product::factory()->create(['name' => 'قميص', 'base_price' => 100, 'manage_stock' => true, 'stock_quantity' => 10]);
        $this->city = $this->city('رام الله', ['المصيون']);
        $this->zone([$this->city]);
        $this->actingAs($this->buyer);
        $this->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1])->assertSessionHasNoErrors();
    }

    public function test_the_form_is_prefilled_from_the_default_address(): void
    {
        $far = $this->city('بعيدة');
        $service = app(AddressService::class);
        $service->create($this->buyer, $this->addressFields(['label' => 'العمل']));
        $default = $service->create($this->buyer, $this->addressFields(['label' => 'البيت', 'is_default' => true]));
        // A city the store no longer delivers to is not offered.
        Address::query()->create([...$this->addressFields(['label' => 'قديم']), 'user_id' => $this->buyer->id, 'city_id' => $far->id, 'district_id' => null]);

        $props = $this->props('/ar/checkout', 'Store/Storefront/Checkout');

        $this->assertSame(['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0599000111'], $props['account']);
        $this->assertSame(['البيت', 'العمل'], array_column($props['addresses'], 'label'));
        $this->assertSame($default->id, $props['addresses'][0]['id']);
        $this->assertTrue($props['addresses'][0]['is_default']);
    }

    public function test_a_signed_in_order_gets_the_user(): void
    {
        $this->place()->assertRedirect(route('store.checkout.done'));

        $order = Order::query()->sole();
        $this->assertSame($this->buyer->id, $order->user_id);
        $this->assertNull($order->created_by);
    }

    public function test_a_strangers_phone_at_checkout_links_and_shows_nothing(): void
    {
        $stranger = $this->guestOrder('0599888777');

        // Typing someone else's phone places an order with it, and nothing more.
        $this->place(['phone' => '0599888777'])->assertSessionHasNoErrors();

        $this->assertNull($stranger->fresh()->user_id);
        $numbers = array_column($this->props('/ar/account/orders', 'Store/Storefront/Account/Orders')['orders']['data'], 'number');
        $this->assertNotContains($stranger->order_number, $numbers);
        $this->assertCount(1, $numbers);
        $this->get($this->siteUrl('/ar/account/orders/'.$stranger->order_number))->assertNotFound();
    }

    public function test_save_this_address(): void
    {
        $this->place(['save_address' => true, 'address_label' => 'البيت'])->assertSessionHasNoErrors();

        $address = Address::query()->sole();
        $this->assertSame([$this->buyer->id, 'البيت', 'شارع الإرسال 5', true], [$address->user_id, $address->label, $address->address_line, $address->is_default]);
    }

    public function test_the_coupon_limit_counts_the_users_orders_with_another_phone(): void
    {
        $this->coupon('ONCE');
        Order::query()->create(['order_number' => 'OLD-1', 'sub_total' => 100, 'status' => OrderStatus::DELIVERED, 'contact_phone' => '0599777666', 'coupon_code' => 'ONCE', 'user_id' => $this->buyer->id]);

        $this->place(['coupon' => 'ONCE', 'phone' => '0599123123'])->assertSessionHasErrors('coupon');

        $quote = $this->props('/ar/checkout?coupon=ONCE', 'Store/Storefront/Checkout')['quote'];
        $this->assertFalse($quote['coupon']['valid']);
    }

    public function test_guest_checkout_still_works(): void
    {
        auth()->logout();
        $token = (string) $this->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1])->getCookie(CartCookie::NAME)?->getValue();

        $this->withCookie(CartCookie::NAME, $token)->place()->assertRedirect(route('store.checkout.done'));

        $this->assertNull(Order::query()->sole()->user_id);
        $this->assertSame(Order::query()->sole()->order_number, $this->props('/ar/checkout/done', 'Store/Storefront/CheckoutDone')['order']['number']);
    }

    private function place(array $overrides = []): TestResponse
    {
        return $this->from($this->siteUrl('/ar/checkout'))->post($this->siteUrl('/ar/checkout'), [
            'submit_token' => (string) Str::uuid(),
            'name' => 'Buyer',
            'phone' => '0599000111',
            'email' => 'buyer@example.com',
            'city_id' => $this->city->id,
            'district_id' => $this->city->districts()->first()->id,
            'address' => 'شارع الإرسال 5',
            'payment_method' => 'cash',
            ...$overrides,
        ]);
    }

    private function guestOrder(string $phone): Order
    {
        return Order::query()->create(['order_number' => 'G'.random_int(10000, 99999), 'sub_total' => 50, 'total' => 50, 'status' => OrderStatus::DELIVERED, 'contact_phone' => $phone]);
    }

    private function addressFields(array $overrides = []): array
    {
        return [
            'label' => 'البيت',
            'recipient_name' => 'Buyer',
            'phone' => '0599000111',
            'city_id' => $this->city->id,
            'district_id' => $this->city->districts()->first()->id,
            'address_line' => 'شارع الإرسال 5',
            'is_default' => false,
            ...$overrides,
        ];
    }

    private function coupon(string $code): Offer
    {
        return Offer::query()->create([
            'code' => $code,
            'type' => OfferType::FIXED,
            'value' => 10,
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
