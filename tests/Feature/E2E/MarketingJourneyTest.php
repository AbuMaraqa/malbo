<?php

namespace Tests\Feature\E2E;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Store\Entities\Cart;
use Modules\Store\Mail\CartReminderMail;
use Modules\Store\Services\AbandonedCartService;
use Modules\Store\Settings\MarketingSettings;
use Modules\Store\Support\CartCookie;
use Modules\Store\Support\MarketingConsent;

/**
 * Marketing as one story: Meta events with the same ids in the page and the Conversions API, a
 * cart left behind with the buyer's consent, the reminder and its link back, the order that
 * recovers it, and the catalog feed with storefront prices only.
 */
class MarketingJourneyTest extends E2ETestCase
{
    private const G = 'http://base.google.com/ns/1.0';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $settings = app(MarketingSettings::class);
        foreach ([
            'pixel_enabled' => true, 'meta_pixel_id' => '1234567890', 'capi_enabled' => true, 'meta_capi_token' => 'TEST-TOKEN',
            'cart_reminders_enabled' => true, 'abandoned_after_minutes' => 60, 'reminder_delays' => [60, 1440],
        ] as $name => $value) {
            $settings->{$name} = $value;
        }
        $settings->save();
    }

    public function test_events_reminder_recovery_and_feed(): void
    {
        $demo = $this->demo();
        $jeans = $demo->item('slim-jeans', 'navy', '32');
        $this->withCookie(MarketingConsent::COOKIE, 'granted');

        // ViewContent: the page and the server send one event id.
        $view = collect($this->page('/ar/product/'.$demo->products['slim-jeans']->slug, 'Store/Storefront/ProductPage')['marketing']['events'])->firstWhere('name', 'ViewContent');
        $this->assertSame($view['id'], $this->sent('ViewContent')['event_id']);
        $jeansSkus = collect($demo->items)->filter(fn ($item, string $key) => str_starts_with($key, 'slim-jeans.'))->pluck('sku')->all();
        $this->assertContains($view['data']['content_ids'][0], $jeansSkus, 'the variant shown');

        // AddToCart at the sale price (179), then the buyer agrees to a reminder and leaves.
        $cart = $this->addToCart($jeans, 1);
        $added = $this->sent('AddToCart');
        $this->assertEquals(179.0, $added['custom_data']['value']);
        $this->assertSame([$jeans->sku], $added['custom_data']['content_ids']);

        $this->withCredentials()->withCookie(CartCookie::NAME, $cart)
            ->postJson($this->siteUrl('/ar/cart/contact'), ['remind' => true, 'email' => 'Leaving@Example.com'])->assertOk()->assertJson(['saved' => true]);
        $model = Cart::query()->where('token', $cart)->sole();
        $this->assertSame('leaving@example.com', $model->contact_email);

        // Not yet abandoned; an hour later the reminder goes, once.
        $this->artisan('store:send-cart-reminders')->assertSuccessful();
        $toBuyer = fn (CartReminderMail $mail) => $mail->hasTo('leaving@example.com');
        Mail::assertNotSent(CartReminderMail::class, $toBuyer);
        $this->travel(61)->minutes();
        $this->artisan('store:send-cart-reminders')->assertSuccessful();
        $this->artisan('store:send-cart-reminders')->assertSuccessful();
        $this->assertCount(1, Mail::sent(CartReminderMail::class, $toBuyer));
        $restore = Mail::sent(CartReminderMail::class, $toBuyer)->first()->restoreUrl;

        // The link opens the cart in another browser; the order recovers it.
        $this->defaultCookies = [MarketingConsent::COOKIE => 'granted'];
        $opened = $this->get($restore)->assertRedirect(route('store.cart'));
        $this->assertSame($cart, $opened->getCookie(CartCookie::NAME)?->getValue());

        $this->checkout($cart, ['email' => 'leaving@example.com'])->assertSessionHasNoErrors()->assertRedirect(route('store.checkout.done'));
        $order = $this->lastOrder();
        $this->assertTrue(app(AbandonedCartService::class)->recovered($model->fresh()));
        $this->actingAs($this->admin())->get($this->siteUrl('/ar/admin/store/abandoned-carts?filter[status]=recovered'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $model->id && $row['recovered']))->etc());

        // Purchase: one id, "order.<number>", on the page and to Meta, sent once however often the page opens.
        $this->app['auth']->forgetGuards();
        $done = $this->page('/ar/checkout/done', 'Store/Storefront/CheckoutDone', cart: $cart);
        $this->page('/ar/checkout/done', 'Store/Storefront/CheckoutDone', cart: $cart);
        $purchase = collect($done['marketing']['events'])->firstWhere('name', 'Purchase');
        $this->assertSame('order.'.$order->order_number, $purchase['id']);
        $purchases = array_values(array_filter($this->events(), fn (array $event) => $event['event_name'] === 'Purchase'));
        $this->assertCount(1, $purchases);
        $this->assertSame($purchase['id'], $purchases[0]['event_id']);
        $this->assertEquals((float) $order->total, $purchases[0]['custom_data']['value']);

        // The catalog feed: every demo item, at storefront prices, never a wholesale or cost price.
        $response = $this->get($this->siteUrl('/feeds/meta-catalog.xml'))->assertOk();
        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));
        $items = [];
        foreach (simplexml_load_string($response->getContent())->channel->item as $item) {
            $row = array_map('strval', (array) $item->children(self::G));
            $items[$row['id']] = $row;
        }

        $this->assertArrayHasKey($jeans->sku, $items);
        $this->assertSame('219.00 ILS', $items[$jeans->sku]['price']);
        $this->assertSame('179.00 ILS', $items[$jeans->sku]['sale_price']);
        $this->assertSame('149.00 ILS', $items[$demo->item('oxford-shirt', 'white', 'M')->sku]['price']);
        $this->assertArrayHasKey($demo->bundles['summer-set']->sku, $items, 'a set is one catalog item');
        $this->assertStringNotContainsString('80.00', $response->getContent(), 'the oxford\'s wholesale price');

        $csv = $this->get($this->siteUrl('/feeds/meta-catalog.csv'))->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $csv->headers->get('Content-Type'));
        $this->assertStringContainsString($jeans->sku, (string) $csv->getContent());
    }

    /**
     * @return list<array<string, mixed>> every event posted to the Conversions API so far
     */
    private function events(): array
    {
        $events = [];

        foreach (Http::recorded(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://graph.facebook.com/')) as [$request]) {
            array_push($events, ...$request->data()['data']);
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function sent(string $name): array
    {
        $matching = array_values(array_filter($this->events(), fn (array $event) => $event['event_name'] === $name));
        $this->assertNotEmpty($matching, "{$name} was not sent");

        return end($matching);
    }
}
