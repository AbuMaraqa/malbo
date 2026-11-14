<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Product;
use Modules\Items\Services\InventoryService;
use Modules\Store\Services\CartService;
use Modules\Store\Services\CheckoutService;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Staff alerts never hold up the buyer: they are sent once the response is out (Laravel's deferred
 * callbacks, no queue worker needed), and never fail what the buyer did.
 */
class StaffAlertsAfterResponseTest extends StorefrontTestCase
{
    use MakesShippingZones;

    public function test_the_new_order_alert_is_sent_after_the_response(): void
    {
        Notification::fake();
        $clerk = User::forceCreate(['name' => 'Clerk', 'email' => 'clerk-'.Str::random(6).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Order View List');
        $product = Product::factory()->create(['manage_stock' => false]);
        $city = $this->city();
        $this->zone([$city]);

        $token = (string) $this->post($this->siteUrl('/ar/cart'), ['product_id' => $product->id, 'quantity' => 1])->getCookie(CartCookie::NAME)?->getValue();
        $cart = app(CartService::class)->current(request()->duplicate(cookies: [CartCookie::NAME => $token]));

        app(CheckoutService::class)->place($cart, [
            'name' => 'Buyer', 'phone' => '0599123456', 'email' => null, 'city_id' => $city->id, 'district_id' => null,
            'address' => 'شارع', 'notes' => null, 'coupon' => null, 'payment_method' => 'cash',
        ]);

        Notification::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke();

        Notification::assertSentTo($clerk, GeneralNotification::class);
    }

    public function test_the_low_stock_alert_is_sent_after_the_response(): void
    {
        Notification::fake();
        $staff = User::forceCreate(['name' => 'Stock', 'email' => 'stock-'.Str::random(6).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Product Update');
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 5, 'low_stock_threshold' => 3]);

        $inventory = app(InventoryService::class);
        $inventory->sell([$product->id => 3]);

        Notification::assertNothingSent();
        $this->assertSame([$product->id], $inventory->lowStock, 'the low product is known at once');

        app(DeferredCallbackCollection::class)->invoke();

        Notification::assertSentTo($staff, GeneralNotification::class);
    }
}
