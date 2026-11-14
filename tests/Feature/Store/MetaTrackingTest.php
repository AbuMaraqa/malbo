<?php

namespace Tests\Feature\Store;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Store\Services\Marketing\MetaUserData;
use Modules\Store\Support\CartCookie;
use Modules\Store\Support\MarketingConsent;
use Tests\Feature\Store\Concerns\MakesMarketing;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Meta Pixel and Conversions API: the browser and the server send the same events with the same
 * ids, only on storefront pages, only after the visitor accepted marketing cookies, and never with
 * a wholesale or cost price.
 */
class MetaTrackingTest extends StorefrontTestCase
{
    use MakesMarketing;
    use MakesShippingZones;

    private const CAPI = 'https://graph.facebook.com/v23.0/1234567890/events';

    private Product $shirt;

    private Product $medium;

    /** What the fake Graph API answers. */
    private int $graphStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => fn () => Http::response($this->graphStatus === 200 ? ['events_received' => 1] : ['error' => ['message' => 'Invalid token']], $this->graphStatus)]);
        Notification::fake();
        $this->marketing();

        $this->shirt = Product::factory()->create(['name' => 'قميص', 'slug' => 'shirt', 'sku' => 'SHIRT', 'base_price' => 100, 'cost_price' => 37.13, 'wholesale_price' => 41.17]);
        $size = $this->sizeAttribute();
        $this->medium = $this->variantOf($this->shirt, [$this->value($size, 'M')], ['sku' => 'SHIRT-M', 'base_price' => 100, 'sale_price' => 80, 'cost_price' => 37.13, 'wholesale_price' => 41.17]);
    }

    public function test_the_capi_payload_shape(): void
    {
        $this->consented()->get($this->siteUrl('/ar/product/shirt'))->assertOk();

        $view = $this->sent('ViewContent');
        $this->assertSame(self::CAPI, $view['url']);
        $this->assertSame('TEST-TOKEN', $view['body']['access_token']);
        $this->assertArrayNotHasKey('test_event_code', $view['body']);

        $event = $view['event'];
        $this->assertSame('website', $event['action_source']);
        $this->assertIsInt($event['event_time']);
        $this->assertEqualsWithDelta(time(), $event['event_time'], 60);
        $this->assertSame($this->siteUrl('/ar/product/shirt'), $event['event_source_url']);
        $this->assertTrue(Str::isUuid($event['event_id']));
        $this->assertSame('127.0.0.1', $event['user_data']['client_ip_address']);
        $this->assertSame('Symfony', $event['user_data']['client_user_agent']);
        $this->assertSame(['currency' => 'ILS', 'value' => 80.0, 'content_ids' => ['SHIRT-M'], 'content_type' => 'product', 'content_name' => 'قميص'], $event['custom_data']);

        $this->marketing(['meta_test_event_code' => 'TEST123']);
        $this->consented()->get($this->siteUrl('/ar/shop'))->assertOk();
        $this->assertSame('TEST123', $this->sent('PageView', last: true)['body']['test_event_code']);
    }

    public function test_user_data_is_hashed(): void
    {
        $buyer = $this->customer(['email' => ' Buyer@Example.COM ', 'phone' => '0599 123 456']);

        $this->actingAs($buyer)->consented()
            ->withUnencryptedCookie('_fbp', 'fb.1.1700000000.123')
            ->withUnencryptedCookie('_fbc', 'fb.1.1700000000.AbCd')
            ->get($this->siteUrl('/ar/product/shirt'))->assertOk();

        $user = $this->sent('ViewContent')['event']['user_data'];
        $this->assertSame([hash('sha256', 'buyer@example.com')], $user['em']);
        $this->assertSame([hash('sha256', '970599123456')], $user['ph']);
        $this->assertSame([hash('sha256', (string) $buyer->id)], $user['external_id']);
        $this->assertSame('fb.1.1700000000.123', $user['fbp']);
        $this->assertSame('fb.1.1700000000.AbCd', $user['fbc']);

        // Israeli numbers get 972; a number typed with its country code keeps it; nothing typed, nothing sent.
        $this->assertSame(hash('sha256', '972525551234'), MetaUserData::phone('052-555-1234'));
        $this->assertSame(hash('sha256', '972525551234'), MetaUserData::phone('+972 52 555 1234'));
        $this->assertSame(hash('sha256', '970599123456'), MetaUserData::phone('00970599123456'));
        $this->assertNull(MetaUserData::phone('  '));
        $this->assertNull(MetaUserData::email(null));
    }

    public function test_page_and_capi_share_the_event_ids(): void
    {
        $marketing = $this->marketingProp('/ar/product/shirt');

        $this->assertSame('granted', $marketing['consent']);
        $this->assertSame('1234567890', $marketing['pixel_id']);
        $this->assertSame(['PageView', 'ViewContent'], array_column($marketing['events'], 'name'));
        $this->assertSame(['SHIRT-M'], $marketing['events'][1]['data']['content_ids']);
        $this->assertEqualsCanonicalizing(array_column($marketing['events'], 'id'), array_column(array_column($this->payloads(), 'event'), 'event_id'));
    }

    public function test_add_to_cart_and_checkout_events(): void
    {
        $token = (string) $this->consented()->from($this->siteUrl('/ar/product/shirt'))
            ->post($this->siteUrl('/ar/cart'), ['product_id' => $this->medium->id, 'quantity' => 2])
            ->assertSessionHasNoErrors()
            ->getCookie(CartCookie::NAME)?->getValue();

        $added = $this->sent('AddToCart')['event'];
        $this->assertSame(['currency' => 'ILS', 'value' => 160.0, 'content_ids' => ['SHIRT-M'], 'content_type' => 'product', 'contents' => [['id' => 'SHIRT-M', 'quantity' => 2, 'item_price' => 80.0]]], $added['custom_data']);
        $this->assertSame($this->siteUrl('/ar/product/shirt'), $added['event_source_url']);

        // The page the buyer lands on fires the same AddToCart, and does not count a new visit.
        $landing = $this->consented()->withCookie(CartCookie::NAME, $token)->marketingProp('/ar/product/shirt');
        $this->assertSame([['AddToCart', $added['event_id']]], array_map(fn (array $event) => [$event['name'], $event['id']], $landing['events']));

        $checkout = $this->consented()->withCookie(CartCookie::NAME, $token)->marketingProp('/ar/checkout');
        $initiate = collect($checkout['events'])->firstWhere('name', 'InitiateCheckout');
        $this->assertEquals(['currency' => 'ILS', 'value' => 160.0, 'content_ids' => ['SHIRT-M'], 'content_type' => 'product', 'contents' => [['id' => 'SHIRT-M', 'quantity' => 2, 'item_price' => 80.0]], 'num_items' => 2], $initiate['data']);
        $this->assertSame($initiate['id'], $this->sent('InitiateCheckout')['event']['event_id']);
    }

    public function test_purchase_is_sent_once_per_order(): void
    {
        $city = $this->city();
        $this->zone([$city], ['fee' => 20]);
        $token = $this->addToCart();

        $this->consented()->withCookie(CartCookie::NAME, $token)->post($this->siteUrl('/ar/checkout'), [
            'submit_token' => (string) Str::uuid(), 'name' => 'Buyer', 'phone' => '0599123456', 'email' => 'Buyer@Example.com',
            'city_id' => $city->id, 'address' => 'شارع', 'payment_method' => 'cash',
        ])->assertRedirect(route('store.checkout.done'));

        $order = Order::query()->sole();
        $first = $this->consented()->marketingProp('/ar/checkout/done');
        $second = $this->consented()->marketingProp('/ar/checkout/done');

        $purchase = collect($first['events'])->firstWhere('name', 'Purchase');
        $this->assertSame('order.'.$order->order_number, $purchase['id']);
        $this->assertSame($purchase['id'], collect($second['events'])->firstWhere('name', 'Purchase')['id']);
        $this->assertEquals(['currency' => 'ILS', 'value' => 180.0, 'content_ids' => ['SHIRT-M'], 'content_type' => 'product', 'contents' => [['id' => 'SHIRT-M', 'quantity' => 2, 'item_price' => 80.0]], 'num_items' => 2], $purchase['data']);

        $sent = array_values(array_filter($this->payloads(), fn (array $payload) => $payload['event']['event_name'] === 'Purchase'));
        $this->assertCount(1, $sent);
        $this->assertSame($purchase['id'], $sent[0]['event']['event_id']);
        $this->assertSame([hash('sha256', 'buyer@example.com')], $sent[0]['event']['user_data']['em']);
    }

    public function test_nothing_is_sent_without_consent(): void
    {
        $undecided = $this->marketingProp('/ar/product/shirt', consent: null);
        $this->assertSame(['consent' => null, 'pixel_id' => null, 'events' => []], $undecided);

        $declined = $this->withCookie(MarketingConsent::COOKIE, 'denied')->marketingProp('/ar/product/shirt', consent: null);
        $this->assertSame(['consent' => 'denied', 'pixel_id' => null, 'events' => []], $declined);

        $this->withCookie(MarketingConsent::COOKIE, 'denied')->post($this->siteUrl('/ar/cart'), ['product_id' => $this->medium->id, 'quantity' => 1]);

        $this->assertSame([], $this->payloads());
    }

    public function test_nothing_is_sent_when_disabled(): void
    {
        $this->marketing(['pixel_enabled' => false, 'capi_enabled' => false]);
        $this->assertNull($this->marketingProp('/ar/product/shirt'));

        // The Pixel alone: events reach the page, the server posts nothing.
        $this->marketing(['pixel_enabled' => true, 'capi_enabled' => false]);
        $this->assertSame(['PageView', 'ViewContent'], array_column($this->marketingProp('/ar/product/shirt')['events'], 'name'));

        // The server alone: the page loads no Pixel.
        $this->marketing(['pixel_enabled' => false, 'capi_enabled' => true]);
        $this->assertSame(['consent' => 'granted', 'pixel_id' => null, 'events' => []], $this->marketingProp('/ar/product/shirt'));
        $this->assertSame(['PageView', 'ViewContent'], array_column(array_column($this->payloads(), 'event'), 'event_name'));
    }

    public function test_nothing_is_sent_from_wholesale_pages(): void
    {
        $this->consented()->get($this->siteUrl('/ar/wholesale/apply'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('marketing', null)->etc());

        $this->assertSame([], $this->payloads());
    }

    public function test_no_wholesale_or_cost_price_in_any_payload(): void
    {
        $city = $this->city();
        $this->zone([$city]);
        $props = [$this->marketingProp('/ar/product/shirt')];
        $token = $this->addToCart();
        $props[] = $this->consented()->withCookie(CartCookie::NAME, $token)->marketingProp('/ar/checkout');

        $everything = json_encode([...$props, ...$this->payloads()]);

        $this->assertNotEmpty($this->payloads());
        foreach (['37.13', '41.17', '37.1', '41.1'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything);
        }
    }

    public function test_a_failed_post_is_logged_not_thrown(): void
    {
        $this->graphStatus = 400;
        Log::spy();

        $this->consented()->get($this->siteUrl('/ar/product/shirt'))->assertOk();

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Meta Conversions API'))->atLeast()->once();
    }

    public function test_one_capi_call_per_request(): void
    {
        $this->consented()->get($this->siteUrl('/ar/product/shirt'))->assertOk();

        $calls = Http::recorded(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://graph.facebook.com/'));
        $this->assertCount(1, $calls);
        $this->assertSame(['PageView', 'ViewContent'], array_column($calls->first()[0]->data()['data'], 'event_name'));
    }

    public function test_event_addresses_carry_no_query(): void
    {
        $this->consented()->get($this->siteUrl('/ar/product/shirt?fbclid=abc123&token=secret'))->assertOk();

        $this->assertSame($this->siteUrl('/ar/product/shirt'), $this->sent('ViewContent')['event']['event_source_url']);
    }

    public function test_the_unsubscribe_page_builds_no_events(): void
    {
        $url = app(\Modules\Store\Services\OptOutService::class)->url('email', 'buyer@example.com', 'ar');

        $marketing = null;
        $this->consented()->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$marketing) {
            $marketing = $page->toArray()['props']['marketing'];
            $page->etc();
        });

        $this->assertSame(['consent' => 'granted', 'pixel_id' => null, 'events' => []], $marketing);
        $this->assertSame([], $this->payloads());
    }

    public function test_declining_expires_the_pixel_cookies(): void
    {
        $this->post($this->siteUrl('/ar/consent'), ['choice' => 'denied'])->assertCookieExpired('_fbp')->assertCookieExpired('_fbc');
    }

    public function test_the_consent_choice_is_a_cookie(): void
    {
        $this->post($this->siteUrl('/ar/consent'), ['choice' => 'maybe'])->assertSessionHasErrors('choice')->assertCookieMissing(MarketingConsent::COOKIE);
        $this->post($this->siteUrl('/ar/consent'), ['choice' => 'granted'])->assertCookie(MarketingConsent::COOKIE, 'granted');
        $this->post($this->siteUrl('/ar/consent'), ['choice' => 'denied'])->assertCookie(MarketingConsent::COOKIE, 'denied');
    }

    private function consented(): static
    {
        return $this->withCookie(MarketingConsent::COOKIE, 'granted');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function marketingProp(string $path, ?string $consent = 'granted'): ?array
    {
        $request = $consent ? $this->withCookie(MarketingConsent::COOKIE, $consent) : $this;
        $marketing = null;

        $request->get($this->siteUrl($path))->assertOk()->assertInertia(function (Assert $page) use (&$marketing) {
            $marketing = $page->toArray()['props']['marketing'] ?? null;
            $page->etc();
        });

        return $marketing;
    }

    /**
     * @return list<array{url: string, body: array<string, mixed>, event: array<string, mixed>}>
     */
    private function payloads(): array
    {
        // One call per request carries all its events: one entry per event.
        $events = [];

        foreach (Http::recorded(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://graph.facebook.com/')) as [$request]) {
            foreach ($request->data()['data'] as $event) {
                $events[] = ['url' => $request->url(), 'body' => $request->data(), 'event' => $event];
            }
        }

        return $events;
    }

    /**
     * @return array{url: string, body: array<string, mixed>, event: array<string, mixed>}
     */
    private function sent(string $name, bool $last = false): array
    {
        $matching = array_values(array_filter($this->payloads(), fn (array $payload) => $payload['event']['event_name'] === $name));
        $this->assertNotEmpty($matching, "{$name} was not sent");

        return $last ? end($matching) : $matching[0];
    }

    private function addToCart(): string
    {
        return (string) $this->post($this->siteUrl('/ar/cart'), ['product_id' => $this->medium->id, 'quantity' => 2])
            ->assertSessionHasNoErrors()
            ->getCookie(CartCookie::NAME)?->getValue();
    }

    private function customer(array $attributes): User
    {
        return User::forceCreate(['name' => 'Buyer', 'email' => 'b-'.Str::random(6).'@x.test', 'password' => 'secret-password', 'email_verified_at' => now(), ...$attributes])
            ->assignRole(UserRole::CUSTOMER->value);
    }
}
