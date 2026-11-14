<?php

namespace Tests\Feature\E2E;

use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Jobs\SendStockAlerts;
use Modules\Store\Mail\BackInStockMail;
use Modules\Wholesale\Entities\Merchant;

/**
 * One stock for both channels: the storefront and the wholesale portal racing for the last units
 * never sell more than there is; staff hear when an item runs low; buyers waiting for it hear when
 * it is back.
 */
class SharedInventoryTest extends E2ETestCase
{
    public function test_b2c_and_b2b_never_oversell_the_last_units(): void
    {
        $demo = $this->demo();
        /** @var Merchant $merchant */
        $merchant = $demo->extra['merchants']['approved'];
        $item = $demo->item('chinos', 'navy', '32');

        // Staff count the shelf: three left.
        $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/stock/adjust'), [
            'product_id' => $item->id, 'delta' => 3 - $this->stock($item), 'reason' => 'جرد',
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->stock($item));

        // Both channels put two in their carts while three are there.
        $cart = $this->addToCart($item, 2);
        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$demo->products['chinos']->id), ['quantities' => [$item->id => 2]])->assertSessionHasNoErrors();

        // The buyer checks out first; the merchant's two are no longer there.
        $this->checkout($cart)->assertSessionHasNoErrors();
        $this->assertSame(1, $this->stock($item));

        $this->wholesaleCheckout($merchant)->assertSessionHasErrors();
        $this->assertSame(0, Order::query()->where('channel', OrderChannel::B2B)->where('user_id', $merchant->user_id)->where('status', OrderStatus::AWAITING_CONFIRMATION)->where('created_at', '>', now()->subMinute())->count());
        $this->assertSame(1, $this->stock($item));

        // The merchant takes the last one; then nobody can buy it.
        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$demo->products['chinos']->id), ['quantities' => [$item->id => 1]])->assertSessionHasNoErrors();
        $this->wholesaleCheckout($merchant)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->stock($item));

        $this->app['auth']->forgetGuards();
        unset($this->defaultCookies['store_cart']);
        $this->post($this->siteUrl('/ar/cart'), ['product_id' => $item->id, 'quantity' => 1])->assertSessionHasErrors();
        $this->actingAs($merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$demo->products['chinos']->id), ['quantities' => [$item->id => 1]])->assertSessionHasErrors('quantities');

        // A cart filled before the stock ran out cannot be checked out either.
        $late = $this->addToCartBefore($item);
        $this->checkout($late)->assertSessionHasErrors();
        $this->assertSame(0, $this->stock($item));
        $this->assertSame(0, (int) StockMovement::query()->where('product_id', $item->id)->sum('quantity'), 'the ledger agrees');
    }

    public function test_staff_hear_when_an_item_runs_low_and_buyers_when_it_is_back(): void
    {
        $demo = $this->demo();
        $item = $demo->item('bomber', 'black', 'L'); // low at 3
        $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/stock/adjust'), ['product_id' => $item->id, 'delta' => 5 - $this->stock($item), 'reason' => 'جرد'])->assertSessionHasNoErrors();
        $this->assertSame(5, $this->stock($item));
        $this->admin();
        app(DeferredCallbackCollection::class)->invoke(); // what the demo left to send
        Notification::fake();

        $this->checkout($this->addToCart($item, 2))->assertSessionHasNoErrors();
        app(DeferredCallbackCollection::class)->invoke();
        Notification::assertSentTo($this->admin(), GeneralNotification::class, fn (GeneralNotification $notification) => $notification->title === 'مخزون منخفض' && str_contains($notification->message, 'جاكيت بومبر'));

        // Sold out; a buyer asks to be told.
        $order = $this->lastOrder();
        $this->checkout($this->addToCart($item, 3))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->stock($item));
        $this->from($this->siteUrl('/ar/product/'.$demo->products['bomber']->slug))
            ->post($this->siteUrl('/ar/stock-alerts'), ['product_id' => $item->id, 'email' => 'waiting@example.com'])->assertSessionHasNoErrors();
        $alert = StockAlert::query()->where('email', 'waiting@example.com')->sole();

        // A cancellation brings units back: the alert is queued, then sent once.
        Queue::fake();
        $this->adminStatus($order, OrderStatus::CANCELLED)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->stock($item));
        Queue::assertPushed(SendStockAlerts::class, fn (SendStockAlerts $job) => in_array($item->id, $job->productIds, true));

        Mail::fake();
        Queue::pushed(SendStockAlerts::class)->first()->handle(app(\Modules\Store\Services\StockAlertService::class));
        Mail::assertSent(BackInStockMail::class, fn (BackInStockMail $mail) => $mail->hasTo('waiting@example.com'));
        $this->assertNotNull($alert->fresh()->notified_at);
    }

    private function wholesaleCheckout(Merchant $merchant): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'address_id' => null, 'notes' => null, 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ]);
    }

    /**
     * A cart that took the item while it was there (written as the cart page left it).
     */
    private function addToCartBefore(\Modules\Items\Entities\Product $item): string
    {
        $cart = \Modules\Store\Entities\Cart::query()->create(['token' => Str::random(48), 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $cart->items()->create(['product_id' => $item->id, 'quantity' => 1, 'choice_key' => '']);

        return $cart->token;
    }
}
