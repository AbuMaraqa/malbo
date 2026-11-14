<?php

namespace Tests\Feature\E2E;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Services\Demo\DemoStore;
use Modules\Store\Services\Demo\DemoStoreBuilder;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Store\StorefrontTestCase;

/**
 * Whole stories across modules, on the demo store (`malbo:demo`, without pictures): a buyer, a
 * merchant and staff go through the site's routes as a browser would, and each step checks what
 * the business holds afterwards (orders, stock ledger, money).
 */
abstract class E2ETestCase extends StorefrontTestCase
{
    private ?DemoStore $demoStore = null;

    private ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        // A story makes more orders and searches a minute than one buyer would.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function demo(): DemoStore
    {
        return $this->demoStore ??= app(DemoStoreBuilder::class)->build(images: false);
    }

    protected function admin(): User
    {
        return $this->adminUser ??= User::forceCreate([
            'name' => 'E2E Admin', 'email' => 'e2e-admin-'.Str::random(6).'@malbo.test', 'password' => 'password', 'email_verified_at' => now(),
        ])->assignRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * The props a page was sent (asserting its component), as the browser reads them.
     *
     * @return array<string, mixed>
     */
    protected function page(string $path, string $component, ?User $as = null, ?string $cart = null): array
    {
        $props = [];
        $request = $as ? $this->actingAs($as) : $this;

        if ($cart !== null) {
            $request = $request->withCookie(CartCookie::NAME, $cart);
        }

        $request->get($this->siteUrl($path))->assertOk()->assertInertia(function (Assert $page) use ($component, &$props) {
            $page->component($component);
            $props = $page->toArray()['props'];
            $page->etc();
        });

        return $props;
    }

    /**
     * Puts an item (or a set with its pieces chosen) in the cart; returns the cart's cookie.
     *
     * @param  array<int|string, int>  $components  component id => chosen item id
     */
    protected function addToCart(Product $product, int $quantity, ?string $cart = null, ?User $as = null, array $components = [], string $locale = 'ar'): string
    {
        $request = $as ? $this->actingAs($as) : $this->asGuest();

        // Cookies set on the test stay for its next requests: a new cart starts without one.
        unset($this->defaultCookies[CartCookie::NAME]);

        if ($cart !== null) {
            $request = $request->withCookie(CartCookie::NAME, $cart);
        }

        $response = $request->from($this->siteUrl("/{$locale}/product/".($product->parent?->slug ?? $product->slug)))
            ->post($this->siteUrl("/{$locale}/cart"), ['product_id' => $product->id, 'quantity' => $quantity, ...($components ? ['components' => $components] : [])])
            ->assertSessionHasNoErrors();

        return $cart ?? (string) $response->getCookie(CartCookie::NAME)?->getValue();
    }

    /**
     * Cash on delivery checkout of the cart.
     */
    protected function checkout(string $cart, array $fields = [], ?User $as = null): TestResponse
    {
        $city = $this->demo()->cities['center'];
        $request = $as ? $this->actingAs($as) : $this->asGuest();

        return $request->withCookie(CartCookie::NAME, $cart)->from($this->siteUrl('/ar/checkout'))->post($this->siteUrl('/ar/checkout'), [
            'submit_token' => (string) Str::uuid(),
            'name' => 'زبون تجربة',
            'phone' => '0599123123',
            'email' => 'buyer-'.Str::random(5).'@example.com',
            'city_id' => $city->id,
            'district_id' => $city->districts()->value('id'),
            'address' => 'شارع الإرسال 5',
            'notes' => null,
            'coupon' => null,
            'payment_method' => 'cash',
            ...$fields,
        ]);
    }

    /**
     * The next requests without the user an earlier actingAs() signed in.
     */
    protected function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    protected function lastOrder(): Order
    {
        return Order::query()->with('items')->latest('id')->firstOrFail();
    }

    protected function stock(Product $item): int
    {
        return (int) $item->fresh()->stock_quantity;
    }

    /**
     * The ledger rows for this order: type => sum of quantities.
     *
     * @return array<string, int>
     */
    protected function movements(Order $order): array
    {
        return StockMovement::query()->where('order_id', $order->id)->get()
            ->groupBy(fn (StockMovement $movement) => $movement->type->value)
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->all();
    }

    /**
     * Staff save the order form with a new status, the lines as they are (as the edit page sends them).
     */
    protected function adminStatus(Order $order, OrderStatus $status): TestResponse
    {
        $order = $order->fresh(['items']);
        $edit = $this->siteUrl('/ar/admin/orders/'.$order->id.'/edit');

        return $this->actingAs($this->admin())->from($edit)->put($this->siteUrl('/ar/admin/orders/'.$order->id), [
            'user_id' => $order->user_id,
            'branch_id' => $order->branch_id,
            'city_id' => $order->city_id,
            'district_id' => $order->district_id,
            'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $item->quantity])->all(),
            'delivery_fee' => $order->delivery_fee,
            'status' => $status->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'delivery_type' => $order->delivery_type?->value,
            'delivery_address' => $order->delivery_address,
            'contact_phone' => $order->contact_phone,
            'notes' => $order->notes,
            'internal_notes' => $order->internal_notes,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
        ]);
    }

    /**
     * Confirm, prepare, ship and deliver as staff do in the admin (the shipment moves the order).
     */
    protected function deliverInAdmin(Order $order): void
    {
        $admin = $this->actingAs($this->admin());

        $admin->put($this->siteUrl('/ar/admin/orders/'.$order->id.'/confirm'))->assertSessionHasNoErrors();
        $this->adminStatus($order, OrderStatus::PREPARING)->assertSessionHasNoErrors();
        $admin->post($this->siteUrl('/ar/admin/shipments'), ['order_id' => $order->id, 'courier' => 'manual', 'tracking_number' => 'TRK-'.$order->id])->assertSessionHasNoErrors();
        $shipment = $order->fresh()->shipments()->latest('id')->firstOrFail();
        $admin->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $admin->put($this->siteUrl('/ar/admin/shipments/'.$shipment->id.'/status'), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    /**
     * Inertia's PDF/CSV/XML answers: 200, the content type, and a body that is one.
     */
    protected function assertDocument(TestResponse $response, string $type): void
    {
        $response->assertOk();
        $this->assertStringStartsWith($type, (string) $response->headers->get('Content-Type'));

        if ($type === 'application/pdf') {
            $body = $response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $response->streamedContent() : (string) $response->getContent();
            $this->assertStringStartsWith('%PDF', $body);
        }
    }
}
