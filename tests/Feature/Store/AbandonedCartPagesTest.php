<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Services\OrderService;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartReminder;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * The admin's abandoned carts: carts whose buyer agreed to reminders, quiet for the set time, with
 * their value at today's prices; and the ones that became orders after a reminder (recovered).
 */
class AbandonedCartPagesTest extends StorefrontTestCase
{
    use MakesShippingZones;

    private Product $shirt;

    private Product $belt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shirt = Product::factory()->create(['name' => 'قميص', 'base_price' => 120, 'manage_stock' => false]);
        $this->belt = Product::factory()->create(['name' => 'حزام', 'base_price' => 40, 'sale_price' => 30, 'manage_stock' => false]);
    }

    public function test_the_list_shows_value_contact_and_reminders(): void
    {
        $quiet = $this->cart(['contact_email' => 'quiet@example.com', 'last_activity_at' => now()->subHours(2)], [[$this->shirt, 2], [$this->belt, 1]]);
        $this->remind($quiet, 1);
        $this->cart(['contact_email' => 'fresh@example.com', 'last_activity_at' => now()->subMinutes(5)]);
        $this->cart(['contact_email' => 'no-consent@example.com', 'contact_consent_at' => null, 'last_activity_at' => now()->subDay()]);
        $this->cart(['contact_email' => 'empty@example.com', 'last_activity_at' => now()->subDay()], []);
        $recovered = $this->cart(['contact_email' => 'back@example.com', 'last_activity_at' => now()->subDay()]);
        $this->remind($recovered, 1);
        $order = $this->order();
        $recovered->update(['order_id' => $order->id, 'converted_at' => now()]);
        $ordered = $this->cart(['contact_email' => 'direct@example.com', 'last_activity_at' => now()->subDay()]);
        $ordered->update(['order_id' => $order->id, 'converted_at' => now()]);

        $this->page()->assertOk()->assertInertia(function (Assert $page) use ($quiet, $recovered, $order) {
            $rows = collect($page->toArray()['props']['table']['data'])->keyBy('id');

            $this->assertEqualsCanonicalizing([$quiet->id, $recovered->id], $rows->keys()->all());
            $this->assertSame('quiet@example.com', $rows[$quiet->id]['contact']);
            $this->assertSame(3, $rows[$quiet->id]['items']);
            $this->assertSame('270.00 ₪', $rows[$quiet->id]['value']);
            $this->assertSame(1, $rows[$quiet->id]['reminders_sent']);
            $this->assertFalse($rows[$quiet->id]['recovered']);
            $this->assertTrue($rows[$recovered->id]['recovered']);
            $this->assertSame($order->order_number, $rows[$recovered->id]['order_number']);
            $page->component('Store/AbandonedCart/Index')->etc();
        });
    }

    public function test_filters(): void
    {
        $open = $this->cart(['contact_email' => 'open@example.com', 'last_activity_at' => now()->subHours(3), 'locale' => 'en']);
        $reminded = $this->cart(['contact_phone' => '0599123456', 'last_activity_at' => now()->subHours(3)]);
        $this->remind($reminded, 1);
        $recovered = $this->cart(['contact_email' => 'back@example.com', 'last_activity_at' => now()->subDay()]);
        $this->remind($recovered, 1);
        $recovered->update(['order_id' => $this->order()->id]);

        $ids = fn (string $query) => collect($this->page('?'.$query)->assertOk()->viewData('page')['props']['table']['data'])->pluck('id')->sort()->values()->all();

        $this->assertSame([$recovered->id], $ids('filter[status]=recovered'));
        $this->assertEqualsCanonicalizing([$open->id, $reminded->id], $ids('filter[status]=open'));
        $this->assertSame([$open->id], $ids('filter[reminded]=0'));
        $this->assertSame([$open->id], $ids('filter[locale]=en'));
        $this->assertSame([$reminded->id], $ids('filter[search]=0599'));
        $this->assertSame([], $ids('filter[last_activity_at_from]=2999-01-01'));
    }

    public function test_the_detail_page(): void
    {
        $buyer = User::forceCreate(['name' => 'Samir', 'email' => 'samir-'.Str::lower(Str::random(5)).'@example.com', 'password' => 'secret-password', 'phone' => '0599000111']);
        $cart = $this->cart(['user_id' => $buyer->id, 'last_activity_at' => now()->subHours(3)], [[$this->shirt, 1]]);
        $this->remind($cart, 1);

        $this->actingAs($this->staff(['Abandoned Cart View List']))->get($this->siteUrl('/ar/admin/store/abandoned-carts/'.$cart->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Store/AbandonedCart/Show')
                ->where('cart.id', $cart->id)
                ->where('cart.customer', 'Samir')
                ->where('cart.email', $buyer->email)
                ->where('cart.phone', '0599000111')
                ->where('cart.value', '120.00 ₪')
                ->where('lines.0.name', 'قميص')
                ->where('lines.0.quantity', 1)
                ->where('reminders.0.sequence', 1)
                ->where('reminders.0.channel', 'mail')
                ->where('reminders.0.status', 'sent')
                ->etc());

        $this->actingAs($this->staff(['Abandoned Cart View List']))->get($this->siteUrl('/ar/admin/store/abandoned-carts/999999'))->assertNotFound();
    }

    public function test_staff_need_the_permission(): void
    {
        $this->actingAs($this->staff(['Page View List']))->get($this->siteUrl('/ar/admin/store/abandoned-carts'))->assertForbidden();
    }

    private function page(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->staff(['Abandoned Cart View List']))->get($this->siteUrl('/ar/admin/store/abandoned-carts'.$query));
    }

    /**
     * @param  list<array{0: Product, 1: int}>|null  $items
     */
    private function cart(array $attributes, ?array $items = null): Cart
    {
        $cart = Cart::query()->create([
            'uuid' => (string) Str::uuid(),
            'token' => Str::random(48),
            'contact_consent_at' => now()->subDays(2),
            'locale' => 'ar',
            ...$attributes,
        ]);

        foreach ($items ?? [[$this->shirt, 1]] as [$product, $quantity]) {
            $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);
        }

        return $cart;
    }

    private function remind(Cart $cart, int $sequence): void
    {
        CartReminder::query()->create(['cart_id' => $cart->id, 'sequence' => $sequence, 'channel' => 'mail', 'recipient' => 'x@example.com', 'status' => 'sent', 'sent_at' => now()]);
    }

    private function order(): Order
    {
        $city = $this->city();

        return app(OrderService::class)->checkout([
            'customer_name' => 'Buyer', 'contact_phone' => '0599123456', 'delivery_address' => 'شارع', 'city_id' => $city->id,
            'payment_method' => 1, 'delivery_fee' => 0, 'discount' => 0,
        ], [['product_id' => $this->shirt->id, 'quantity' => 1, 'price' => 120]], OrderChannel::B2C);
    }
}
