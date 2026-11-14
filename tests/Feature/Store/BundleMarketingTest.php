<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Product;
use Modules\Store\Services\Marketing\CatalogFeed;
use Modules\Store\Support\CartCookie;
use Modules\Store\Support\MarketingConsent;
use Tests\Feature\Items\Concerns\MakesBundles;
use Tests\Feature\Store\Concerns\MakesMarketing;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * A set is one item for Meta: in the catalog feed and in every event, under its own id.
 */
class BundleMarketingTest extends StorefrontTestCase
{
    use MakesBundles;
    use MakesMarketing;
    use MakesShippingZones;

    private Product $set;

    /** @var array<string, Product> */
    private array $shirts;

    /** @var array<string, Product> */
    private array $trousers;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        Notification::fake();
        $this->marketing();

        [$shirt, $this->shirts] = $this->sized('قميص', ['M' => 3], 120);
        [$trousers, $this->trousers] = $this->sized('بنطال', ['32' => 3], 180);
        $this->set = $this->bundle([[$shirt, 1], [$trousers, 1]], ['name' => 'طقم الصيف', 'slug' => 'summer-set', 'sku' => 'SET-SUMMER', 'base_price' => 300, 'sale_price' => 240]);
    }

    public function test_the_feed_has_one_item_per_bundle_with_its_availability(): void
    {
        $item = collect(app(CatalogFeed::class)->items())->firstWhere('id', 'SET-SUMMER');

        $this->assertNotNull($item);
        $this->assertSame('in stock', $item['availability']);
        $this->assertSame('300.00 ILS', $item['price']);
        $this->assertSame('240.00 ILS', $item['sale_price']);
        $this->assertArrayNotHasKey('item_group_id', $item);

        $this->shirts['M']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        CatalogFeed::forget();

        $this->assertSame('out of stock', collect(app(CatalogFeed::class)->items())->firstWhere('id', 'SET-SUMMER')['availability']);
    }

    public function test_purchase_sends_the_bundle_id_once(): void
    {
        $city = $this->city();
        $this->zone([$city], ['fee' => 20]);
        [$first, $second] = $this->set->bundleComponents()->get()->all();

        $added = $this->post($this->siteUrl('/ar/cart'), ['product_id' => $this->set->id, 'quantity' => 2, 'components' => [$first->id => $this->shirts['M']->id, $second->id => $this->trousers['32']->id]])->assertSessionHasNoErrors();
        $token = (string) $added->getCookie(CartCookie::NAME)?->getValue();

        $this->withCookie(MarketingConsent::COOKIE, 'granted')->withCookie(CartCookie::NAME, $token)->post($this->siteUrl('/ar/checkout'), [
            'submit_token' => (string) Str::uuid(), 'name' => 'Buyer', 'phone' => '0599123456', 'email' => 'buyer@example.com',
            'city_id' => $city->id, 'address' => 'شارع', 'payment_method' => 'cash',
        ])->assertRedirect(route('store.checkout.done'));

        $marketing = null;
        $this->withCookie(MarketingConsent::COOKIE, 'granted')->get($this->siteUrl('/ar/checkout/done'))->assertOk()
            ->assertInertia(function (Assert $page) use (&$marketing) {
                $marketing = $page->toArray()['props']['marketing'];
                $page->etc();
            });

        $purchase = collect($marketing['events'])->firstWhere('name', 'Purchase');
        $this->assertEquals(['currency' => 'ILS', 'value' => 500.0, 'content_ids' => ['SET-SUMMER'], 'content_type' => 'product', 'contents' => [['id' => 'SET-SUMMER', 'quantity' => 2, 'item_price' => 240.0]], 'num_items' => 2], $purchase['data']);

        $viewContent = null;
        $this->withCookie(MarketingConsent::COOKIE, 'granted')->get($this->siteUrl('/ar/product/summer-set'))->assertOk()
            ->assertInertia(function (Assert $page) use (&$viewContent) {
                $viewContent = collect($page->toArray()['props']['marketing']['events'])->firstWhere('name', 'ViewContent');
                $page->etc();
            });
        $this->assertSame(['SET-SUMMER'], $viewContent['data']['content_ids']);
        $this->assertSame(240.0, (float) $viewContent['data']['value']);
    }
}
