<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Services\InventoryService;
use Modules\Store\Contracts\SmsGateway;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Mail\BackInStockMail;
use Modules\Store\Services\OptOutService;
use Tests\Fakes\FakeSmsGateway;
use Tests\Feature\Store\Concerns\MakesMarketing;

/**
 * "Notify me when available": a buyer leaves an email or phone for a variant that cannot be ordered,
 * and hears once, when it can be ordered again.
 */
class StockAlertTest extends StorefrontTestCase
{
    use MakesMarketing;

    private Product $shirt;

    private Product $medium;

    private Product $large;

    private FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->sms = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);

        $this->shirt = Product::factory()->create(['name' => 'قميص', 'slug' => 'shirt']);
        $size = $this->sizeAttribute();
        $this->medium = $this->variantOf($this->shirt, [$this->value($size, 'M')], ['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->large = $this->variantOf($this->shirt, [$this->value($size, 'L', 1)], ['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
    }

    public function test_a_buyer_subscribes_for_an_unavailable_variant(): void
    {
        $this->subscribe(['email' => ' Buyer@Example.COM '])->assertRedirect()->assertSessionHasNoErrors();
        $this->subscribe(['phone' => '0599 123 456', 'product_id' => $this->large->id])->assertSessionHasNoErrors();

        $alert = StockAlert::query()->where('product_id', $this->medium->id)->sole();
        $this->assertSame(['buyer@example.com', null, 'ar', null], [$alert->email, $alert->phone, $alert->locale, $alert->notified_at]);
        $this->assertSame('0599123456', StockAlert::query()->where('product_id', $this->large->id)->sole()->phone);

        $this->subscribe([])->assertSessionHasErrors(['email', 'phone']);
        $this->subscribe(['email' => 'nope'])->assertSessionHasErrors('email');
    }

    public function test_a_duplicate_keeps_one_waiting_alert(): void
    {
        $this->subscribe(['email' => 'buyer@example.com'])->assertSessionHasNoErrors();
        $this->subscribe(['email' => 'BUYER@example.com'])->assertSessionHasNoErrors();
        $this->subscribe(['phone' => '0599123456'])->assertSessionHasNoErrors();
        $this->subscribe(['phone' => '+970 599 123 456'])->assertSessionHasNoErrors();

        $this->assertSame(2, StockAlert::query()->count());

        // Once told, the buyer may ask again for the next time.
        StockAlert::query()->update(['notified_at' => now()]);
        $this->subscribe(['email' => 'buyer@example.com'])->assertSessionHasNoErrors();
        $this->assertSame(1, StockAlert::query()->whereNull('notified_at')->count());
    }

    public function test_an_orderable_item_takes_no_alert(): void
    {
        $this->medium->update(['stock_quantity' => 2, 'stock_status' => 'in_stock']);
        $this->subscribe(['email' => 'buyer@example.com'])->assertSessionHasErrors('product_id');

        // The product itself has variants: one of them is chosen.
        $this->subscribe(['email' => 'buyer@example.com', 'product_id' => $this->shirt->id])->assertSessionHasErrors('product_id');

        $draft = Product::factory()->create(['status' => ProductStatus::DRAFT, 'manage_stock' => true, 'stock_quantity' => 0]);
        $this->subscribe(['email' => 'buyer@example.com', 'product_id' => $draft->id])->assertSessionHasErrors('product_id');

        $this->assertSame(0, StockAlert::query()->count());
    }

    public function test_the_honeypot(): void
    {
        $this->subscribe(['email' => 'bot@example.com', 'website' => 'http://spam.test'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, StockAlert::query()->count());
    }

    public function test_it_is_throttled(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->subscribe(['email' => "buyer{$attempt}@example.com"])->assertSessionHasNoErrors();
        }

        $this->subscribe(['email' => 'buyer6@example.com'])->assertStatus(429);
    }

    public function test_the_transition_notifies_exactly_once(): void
    {
        $this->subscribe(['email' => 'buyer@example.com']);
        $this->subscribe(['phone' => '0599123456']);
        $this->subscribe(['email' => 'other@example.com', 'product_id' => $this->large->id]);

        $inventory = app(InventoryService::class);
        $inventory->receive($this->medium, 3, null, null);
        $this->afterResponse();

        Mail::assertSent(BackInStockMail::class, 1);
        Mail::assertSent(BackInStockMail::class, fn (BackInStockMail $mail) => $mail->hasTo('buyer@example.com')
            && $mail->name === 'قميص'
            && str_contains($mail->url, '/ar/product/shirt')
            && $mail->locale === 'ar');
        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('0599123456', $this->sms->sent[0]['phone']);
        $this->assertStringContainsString('/ar/product/shirt', $this->sms->sent[0]['message']);
        $this->assertStringContainsString('/ar/unsubscribe?', $this->sms->sent[0]['message']);
        $this->assertSame(2, StockAlert::query()->whereNotNull('notified_at')->count());

        // More stock, a sale, the same again: nobody is told twice.
        $inventory->receive($this->medium, 2, null, null);
        $inventory->sell([$this->medium->id => 5]);
        $inventory->receive($this->medium, 1, null, null);
        $this->afterResponse();

        Mail::assertSent(BackInStockMail::class, 1);
        $this->assertCount(1, $this->sms->sent);
        $this->assertNull(StockAlert::query()->where('product_id', $this->large->id)->value('notified_at'));
    }

    public function test_nothing_is_sent_while_still_unavailable(): void
    {
        $this->subscribe(['email' => 'buyer@example.com']);

        // Another variant arrives.
        app(InventoryService::class)->receive($this->large, 4, null, null);
        // This one arrives while the product is off the store.
        $this->shirt->update(['status' => ProductStatus::DRAFT]);
        app(InventoryService::class)->receive($this->medium, 4, null, null);
        $this->afterResponse();

        Mail::assertNothingOutgoing();
        $this->assertNull(StockAlert::query()->sole()->notified_at);
    }

    public function test_an_opted_out_contact_gets_nothing(): void
    {
        $this->subscribe(['email' => 'buyer@example.com']);
        app(OptOutService::class)->optOut('email', 'Buyer@Example.com');

        app(InventoryService::class)->receive($this->medium, 3, null, null);
        $this->afterResponse();

        Mail::assertNothingOutgoing();
    }

    public function test_the_mail_speaks_the_subscribers_language_and_can_be_stopped(): void
    {
        $this->post($this->siteUrl('/en/stock-alerts'), ['product_id' => $this->medium->id, 'email' => 'buyer@example.com'])->assertSessionHasNoErrors();

        app(InventoryService::class)->receive($this->medium, 3, null, null);
        $this->afterResponse();

        Mail::assertSent(BackInStockMail::class, fn (BackInStockMail $mail) => $mail->locale === 'en'
            && str_contains($mail->url, '/en/product/shirt')
            && str_contains($mail->unsubscribeUrl, '/en/unsubscribe'));
    }

    public function test_no_sms_provider_keeps_phone_alerts_waiting(): void
    {
        $this->sms->enabled = false;
        $this->subscribe(['phone' => '0599123456']);
        $this->subscribe(['email' => 'buyer@example.com']);

        app(InventoryService::class)->receive($this->medium, 3, null, null);
        $this->afterResponse();

        $this->assertSame([], $this->sms->sent);
        Mail::assertSent(BackInStockMail::class, 1);
        $this->assertNull(StockAlert::query()->whereNotNull('phone')->sole()->notified_at, 'waiting for a provider');
    }

    public function test_a_contact_waits_for_at_most_ten_items(): void
    {
        $alerts = app(\Modules\Store\Services\StockAlertService::class);

        foreach (range(1, 10) as $i) {
            $alerts->subscribe(Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 0])->id, 'Buyer@Example.com', null, 'ar');
        }

        try {
            $alerts->subscribe(Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 0])->id, 'buyer@example.com', null, 'ar');
            $this->fail('An eleventh waiting alert was taken.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('product_id', $e->errors());
        }

        $this->assertSame(10, StockAlert::query()->where('email', 'buyer@example.com')->count());
    }

    public function test_alerts_go_out_from_a_queued_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->subscribe(['email' => 'buyer@example.com']);

        app(InventoryService::class)->receive($this->medium, 3, null, null);
        $this->afterResponse();

        Mail::assertNothingOutgoing();
        \Illuminate\Support\Facades\Queue::assertPushed(\Modules\Store\Jobs\SendStockAlerts::class, fn ($job) => $job->productIds === [$this->medium->id]);

        // No worker to keep alive: the scheduler drains the database queue every minute.
        $worker = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'queue:work') && str_contains((string) $event->command, '--stop-when-empty'));
        $this->assertCount(1, $worker);
        $this->assertSame('* * * * *', $worker->first()->expression);
    }

    public function test_the_admin_list_groups_by_product(): void
    {
        $this->subscribe(['email' => 'a@example.com']);
        $this->subscribe(['email' => 'b@example.com']);
        $this->subscribe(['email' => 'c@example.com', 'product_id' => $this->large->id]);
        StockAlert::query()->where('email', 'b@example.com')->update(['notified_at' => now()]);

        $this->actingAs($this->staff(['Page View List']))->get($this->siteUrl('/ar/admin/store/stock-alerts'))->assertForbidden();

        $rows = null;
        $this->actingAs($this->staff(['Stock Alert View List']))->get($this->siteUrl('/ar/admin/store/stock-alerts'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $page->component('Store/StockAlert/Index');
                $rows = collect($page->toArray()['props']['table']['data'])->keyBy('product_id');
                $page->etc();
            });

        $this->assertSame([1, 1], [$rows[$this->medium->id]['waiting'], $rows[$this->medium->id]['notified']]);
        $this->assertSame([1, 0], [$rows[$this->large->id]['waiting'], $rows[$this->large->id]['notified']]);
        $this->assertSame('قميص', $rows[$this->medium->id]['name']);
        $this->assertSame('M', $rows[$this->medium->id]['options']);

        $filtered = $this->actingAs($this->staff(['Stock Alert View List']))->get($this->siteUrl('/ar/admin/store/stock-alerts?filter[search]='.urlencode($this->large->sku)))
            ->viewData('page')['props']['table']['data'];
        $this->assertSame([$this->large->id], array_column($filtered, 'product_id'));
    }

    private function subscribe(array $data): TestResponse
    {
        return $this->from($this->siteUrl('/ar/product/shirt'))->post($this->siteUrl('/ar/stock-alerts'), ['product_id' => $this->medium->id, ...$data]);
    }

    private function afterResponse(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }
}
