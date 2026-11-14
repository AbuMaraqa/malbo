<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Product;
use Modules\Store\Contracts\SmsGateway;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartReminder;
use Modules\Store\Mail\CartReminderMail;
use Modules\Store\Services\CartReminderSender;
use Modules\Store\Support\CartCookie;
use Tests\Fakes\FakeSmsGateway;
use Tests\Feature\Store\Concerns\MakesMarketing;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Abandoned carts: a cart with items, not ordered, quiet for a while, whose buyer agreed at checkout
 * to be reminded. Reminders go at the set delays, once each, by email (else SMS), with a signed link
 * that brings the cart back and a way out of marketing messages.
 */
class AbandonedCartTest extends StorefrontTestCase
{
    use MakesMarketing;
    use MakesShippingZones;

    private Product $product;

    private FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->sms = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
        $this->marketing(['pixel_enabled' => false, 'capi_enabled' => false, 'cart_reminders_enabled' => true, 'abandoned_after_minutes' => 60, 'reminder_delays' => [60, 1440]]);
        $this->product = Product::factory()->create(['name' => 'قميص', 'base_price' => 120, 'manage_stock' => false]);
    }

    public function test_contact_is_saved_only_with_consent(): void
    {
        $token = $this->addToCart();

        $this->contact($token, ['remind' => false, 'email' => 'buyer@example.com'])->assertOk();
        $cart = Cart::query()->sole();
        $this->assertNull($cart->contact_email);
        $this->assertNull($cart->contact_consent_at);

        $this->contact($token, ['remind' => true, 'email' => ' Buyer@Example.COM ', 'phone' => '٠٥٩٩ 123 456'])->assertOk();
        $cart->refresh();
        $this->assertSame(['buyer@example.com', '0599123456', 'ar'], [$cart->contact_email, $cart->contact_phone, $cart->locale]);
        $this->assertNotNull($cart->contact_consent_at);

        $this->contact($token, ['remind' => true, 'email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email', 'data');
        $this->contact($token, ['remind' => true, 'phone' => '12'])->assertUnprocessable()->assertJsonValidationErrors('phone', 'data');
        // No cart in this browser: nothing to save.
        $this->withCredentials()->withCookie(CartCookie::NAME, 'x'.Str::random(47))->postJson($this->siteUrl('/ar/cart/contact'), ['remind' => true, 'email' => 'a@b.co'])->assertOk()->assertJson(['saved' => false]);
    }

    public function test_unchecking_withdraws_consent(): void
    {
        $token = $this->addToCart();
        $this->contact($token, ['remind' => true, 'email' => 'buyer@example.com'])->assertOk();

        $this->contact($token, ['remind' => false])->assertOk();

        $cart = Cart::query()->sole();
        $this->assertSame([null, null, null], [$cart->contact_email, $cart->contact_phone, $cart->contact_consent_at]);
    }

    public function test_a_cart_is_abandoned_after_the_set_minutes(): void
    {
        $this->marketing(['abandoned_after_minutes' => 90, 'reminder_delays' => [60, 1440]]);
        $this->consentedCart('buyer@example.com');

        $this->travel(89)->minutes();
        $this->remind();
        Mail::assertNothingOutgoing();

        $this->travel(2)->minutes();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 1);
    }

    public function test_reminders_follow_the_delays(): void
    {
        $cart = $this->consentedCart('buyer@example.com');

        $this->travel(59)->minutes();
        $this->remind();
        Mail::assertNothingOutgoing();

        $this->travel(2)->minutes();
        $this->remind();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 1);

        // The second is due at 24 hours of quiet, but the address had a reminder 23 hours ago: it
        // waits until a day has passed since that one (one reminder a day per recipient).
        $this->travel(1440 - 61 + 1)->minutes();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 1);

        $this->travel(61)->minutes();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 2);

        $this->travel(3)->days();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 2);

        $this->assertSame([[1, 'mail', 'buyer@example.com', 'sent'], [2, 'mail', 'buyer@example.com', 'sent']], CartReminder::query()->where('cart_id', $cart->id)->orderBy('sequence')->get()
            ->map(fn (CartReminder $reminder) => [$reminder->sequence, $reminder->channel, $reminder->recipient, $reminder->status])->all());

        Mail::assertSent(CartReminderMail::class, fn (CartReminderMail $mail) => $mail->hasTo('buyer@example.com')
            && $mail->cart->is($cart)
            && $mail->lines[0]['name'] === 'قميص'
            && $mail->total === '120.00 ₪');
    }

    public function test_a_late_reminder_is_skipped(): void
    {
        $this->consentedCart('buyer@example.com');

        // The scheduler was off (or reminders were just switched on): a reminder 3 days late is not sent.
        $this->travel(3)->days();
        $this->remind();

        Mail::assertNothingOutgoing();
        $this->assertSame(0, CartReminder::query()->count());
    }

    public function test_no_reminder_without_consent(): void
    {
        // An email typed without ticking the box.
        $token = $this->addToCart();
        $this->contact($token, ['remind' => false, 'email' => 'buyer@example.com']);

        // An account's own email, never agreed to (another browser).
        $this->defaultCookies = [];
        $buyer = $this->customer('member@example.com');
        $this->actingAs($buyer)->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        auth()->logout();

        // Reminders switched off.
        $this->travel(61)->minutes();
        $this->remind();
        Mail::assertNothingOutgoing();
        $this->assertSame(2, Cart::query()->count());
    }

    public function test_a_signed_in_buyer_who_agreed_is_reminded_at_the_accounts_email(): void
    {
        $buyer = $this->customer('member@example.com');
        $this->actingAs($buyer)->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->withCredentials()->postJson($this->siteUrl('/ar/cart/contact'), ['remind' => true])->assertOk()->assertJson(['saved' => true]);

        $this->travel(61)->minutes();
        $this->remind();

        Mail::assertSent(CartReminderMail::class, fn (CartReminderMail $mail) => $mail->hasTo('member@example.com'));
    }

    public function test_reminders_off_send_nothing(): void
    {
        $this->consentedCart('buyer@example.com');
        $this->marketing(['cart_reminders_enabled' => false]);

        $this->travel(61)->minutes();
        $this->remind();

        Mail::assertNothingOutgoing();
    }

    public function test_a_converted_cart_gets_no_reminder_and_counts_as_recovered(): void
    {
        $cart = $this->consentedCart('buyer@example.com');
        $this->travel(61)->minutes();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 1);

        $city = $this->city();
        $this->zone([$city]);
        $this->withCookie(CartCookie::NAME, $cart->token)->post($this->siteUrl('/ar/checkout'), [
            'submit_token' => (string) Str::uuid(), 'name' => 'Buyer', 'phone' => '0599123456', 'email' => 'buyer@example.com',
            'city_id' => $city->id, 'address' => 'شارع', 'payment_method' => 'cash',
        ])->assertRedirect(route('store.checkout.done'));

        $this->travel(2)->days();
        $this->remind();

        Mail::assertSent(CartReminderMail::class, 1);
        $this->assertTrue(app(\Modules\Store\Services\AbandonedCartService::class)->recovered($cart->fresh()));
    }

    public function test_an_overlapping_run_sends_nothing_twice(): void
    {
        $cart = $this->consentedCart('buyer@example.com');
        $this->travel(61)->minutes();

        // Another run holds the lock: this one sends nothing.
        $lock = Cache::lock(CartReminderSender::LOCK, 60);
        $this->assertTrue($lock->get());
        $this->assertSame(0, app(CartReminderSender::class)->sendDue());
        $lock->release();

        // Another run already claimed this reminder (its row exists): it is not sent again.
        CartReminder::query()->create(['cart_id' => $cart->id, 'sequence' => 1, 'channel' => 'mail', 'recipient' => 'buyer@example.com', 'status' => 'sending']);
        $this->assertSame(0, app(CartReminderSender::class)->sendDue());

        Mail::assertNothingOutgoing();
    }

    public function test_sms_when_there_is_no_email(): void
    {
        $this->consentedCart(null, '0599 123 456');
        $this->travel(61)->minutes();
        $this->remind();

        Mail::assertNothingOutgoing();
        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('0599123456', $this->sms->sent[0]['phone']);
        $this->assertStringContainsString('/ar/cart/restore/', $this->sms->sent[0]['message']);
        $this->assertStringContainsString('/ar/unsubscribe?', $this->sms->sent[0]['message']);
        $this->assertSame('sms', CartReminder::query()->sole()->channel);
    }

    public function test_no_sms_provider_means_no_sms_reminder_and_no_row(): void
    {
        $this->sms->enabled = false;
        $cart = $this->consentedCart(null, '0599 123 456');
        $this->travel(61)->minutes();
        $this->remind();

        $this->assertSame([], $this->sms->sent);
        $this->assertSame(0, CartReminder::query()->count());
        $this->assertFalse(app(\Modules\Store\Services\AbandonedCartService::class)->recovered($cart->fresh()));
    }

    public function test_the_log_driver_hides_the_number_and_the_links(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        $gateway = new \Modules\Store\Support\LogSmsGateway;

        $this->assertFalse($gateway->enabled());
        $gateway->send('0599123456', 'سلتك بانتظارك: https://store.test/ar/cart/restore/abc?signature=xyz');

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->withArgs(fn (string $line) => str_contains($line, '*******456')
            && ! str_contains($line, '0599123456') && ! str_contains($line, 'restore') && ! str_contains($line, 'signature'))->once();
    }

    public function test_one_reminder_per_recipient_a_day(): void
    {
        $first = $this->consentedCart('buyer@example.com');
        $this->defaultCookies = [];
        $second = $this->consentedCart('BUYER@example.com');

        $this->travel(61)->minutes();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 1);

        // A day later both carts have a reminder due: the address still gets one a day.
        $this->travel(1440)->minutes();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 2);
        $this->assertSame(2, CartReminder::query()->whereIn('cart_id', [$first->id, $second->id])->count());
    }

    public function test_a_guest_sent_to_sign_in_comes_back_to_the_link(): void
    {
        $owner = $this->customer('owner@example.com');
        $this->actingAs($owner)->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1]);
        $cart = Cart::query()->where('user_id', $owner->id)->sole();
        $url = $this->restoreUrl($cart);
        auth()->logout();

        $login = $this->get($url)->assertRedirect()->headers->get('Location');
        $back = urldecode((string) parse_url($login, PHP_URL_QUERY));
        $this->assertSame('back='.substr($url, strlen($this->siteUrl(''))), $back);

        $this->get($login)->assertOk();
        $this->post($this->siteUrl('/ar/account/login'), ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertRedirect($url);
    }

    public function test_restoring_a_guest_cart_while_signed_in_joins_it_to_the_account(): void
    {
        $other = Product::factory()->create(['name' => 'حزام', 'manage_stock' => false]);
        $guest = $this->consentedCart('buyer@example.com');
        $this->defaultCookies = [];

        $member = $this->customer('member@example.com');
        $this->actingAs($member)->post($this->siteUrl('/ar/cart'), ['product_id' => $other->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $mine = Cart::query()->where('user_id', $member->id)->sole();
        app('cookie')->flushQueuedCookies();

        $response = $this->actingAs($member)->get($this->restoreUrl($guest))->assertRedirect(route('store.cart'));

        $this->assertSame($mine->token, $response->getCookie(CartCookie::NAME)?->getValue());
        $this->assertNull(Cart::query()->find($guest->id), 'the guest cart joined the account\'s');
        $this->assertEqualsCanonicalizing([$this->product->id, $other->id], $mine->fresh()->items->pluck('product_id')->all());
    }

    public function test_restoring_a_guest_cart_into_an_account_without_one_gives_it_the_account(): void
    {
        $guest = $this->consentedCart('buyer@example.com');
        $this->defaultCookies = [];
        $member = $this->customer('member@example.com');

        $this->actingAs($member)->get($this->restoreUrl($guest))->assertRedirect(route('store.cart'));

        $this->assertSame($member->id, $guest->fresh()->user_id);
    }

    public function test_the_mail_is_in_the_carts_language(): void
    {
        URL::defaults(['locale' => 'he']);
        app()->setLocale('he');
        $token = (string) $this->post($this->siteUrl('/he/cart'), ['product_id' => $this->product->id, 'quantity' => 1])->assertSessionHasNoErrors()->getCookie(CartCookie::NAME)?->getValue();
        $this->assertNotSame('', $token);
        $this->withCredentials()->withCookie(CartCookie::NAME, $token)->postJson($this->siteUrl('/he/cart/contact'), ['remind' => true, 'email' => 'buyer@example.com'])->assertOk()->assertJson(['saved' => true]);
        $this->assertSame('he', Cart::query()->sole()->locale);
        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->travel(61)->minutes();
        $this->remind();

        Mail::assertSent(CartReminderMail::class, function (CartReminderMail $mail) {
            return $mail->locale === 'he'
                && str_contains($mail->restoreUrl, '/he/cart/restore/')
                && str_contains($mail->render(), 'העגלה שלך מחכה לך');
        });
    }

    public function test_unsubscribe_stops_reminders(): void
    {
        $this->consentedCart('buyer@example.com');
        $this->travel(61)->minutes();
        $this->remind();

        $url = null;
        Mail::assertSent(CartReminderMail::class, function (CartReminderMail $mail) use (&$url) {
            $url = $mail->unsubscribeUrl;

            return true;
        });

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Store/Storefront/Unsubscribe')->where('done', false)->where('contact', 'b***@example.com')->etc());
        $this->post($url)->assertRedirect();
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('done', true)->etc());
        $this->post(str_replace('c=', 'c=x', $url))->assertForbidden();

        $this->travel(1)->days();
        $this->remind();
        Mail::assertSent(CartReminderMail::class, 1);
    }

    public function test_the_restore_link_opens_this_cart_only(): void
    {
        $cart = $this->consentedCart('buyer@example.com');
        $url = $this->restoreUrl($cart);

        // Another browser, with its own cart: the link brings this one in.
        $this->withCookie(CartCookie::NAME, 'x'.Str::random(47));
        $response = $this->get($url)->assertRedirect(route('store.cart'));

        $this->assertSame($cart->token, $response->getCookie(CartCookie::NAME)?->getValue());
        $this->assertSame(1, $this->withCookie(CartCookie::NAME, $cart->token)->props('/ar/cart', 'Store/Storefront/Cart')['summary']['count']);
    }

    public function test_a_restore_link_never_opens_another_accounts_cart(): void
    {
        $owner = $this->customer('owner@example.com');
        $this->actingAs($owner)->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1]);
        $cart = Cart::query()->where('user_id', $owner->id)->sole();
        $url = $this->restoreUrl($cart);
        auth()->logout();
        app('cookie')->flushQueuedCookies(); // the add above queued its own cookie

        $this->get($url)->assertRedirect()->assertCookieMissing(CartCookie::NAME);
        $this->assertStringContainsString('/ar/account/login', $this->get($url)->headers->get('Location'));

        $this->actingAs($this->customer('other@example.com'))->get($url)->assertForbidden();

        $this->actingAs($owner)->get($url)->assertRedirect(route('store.cart'));
    }

    public function test_links_hold_behind_another_host_or_a_proxy(): void
    {
        $this->consentedCart('buyer@example.com');
        $this->travel(61)->minutes();
        $this->remind();

        $mail = null;
        Mail::assertSent(CartReminderMail::class, function (CartReminderMail $sent) use (&$mail) {
            $mail = $sent;

            return true;
        });

        foreach ([$mail->restoreUrl, $mail->unsubscribeUrl] as $url) {
            $this->assertStringStartsWith($this->siteUrl('/ar/'), $url);
            $this->get(str_replace('http://localhost', 'https://shop.example.org', $url))->assertStatus(str_contains($url, 'restore') ? 302 : 200);
        }
    }

    public function test_a_tampered_restore_link_is_refused(): void
    {
        $mine = $this->consentedCart('buyer@example.com');
        $other = Cart::query()->create(['uuid' => (string) Str::uuid(), 'token' => Str::random(48), 'last_activity_at' => now()]);
        $url = $this->restoreUrl($mine);

        $this->get(str_replace($mine->uuid, $other->uuid, $url))->assertForbidden();

        $this->travel(15)->days();
        $this->get($url)->assertForbidden();
    }

    private function addToCart(): string
    {
        return (string) $this->post($this->siteUrl('/ar/cart'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertSessionHasNoErrors()
            ->getCookie(CartCookie::NAME)?->getValue();
    }

    private function contact(string $token, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withCredentials()->withCookie(CartCookie::NAME, $token)->postJson($this->siteUrl('/ar/cart/contact'), $data);
    }

    private function consentedCart(?string $email, ?string $phone = null): Cart
    {
        $token = $this->addToCart();
        $this->contact($token, ['remind' => true, 'email' => $email, 'phone' => $phone])->assertOk();

        return Cart::query()->where('token', $token)->sole();
    }

    private function remind(): void
    {
        $this->artisan('store:send-cart-reminders')->assertSuccessful();
    }

    private function restoreUrl(Cart $cart): string
    {
        // Signed as a path (`signed:relative`): the link holds whatever host the site is reached on.
        return $this->siteUrl(URL::temporarySignedRoute('store.cart.restore', now()->addDays(14), ['locale' => 'ar', 'cart' => $cart->uuid], absolute: false));
    }

    private function customer(string $email): User
    {
        return User::forceCreate(['name' => 'Buyer', 'email' => $email, 'password' => 'secret-password', 'email_verified_at' => now()])
            ->assignRole(UserRole::CUSTOMER->value);
    }
}
