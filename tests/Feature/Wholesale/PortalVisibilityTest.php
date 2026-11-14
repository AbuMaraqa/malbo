<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * Wholesale prices reach approved merchants only: never a guest, a customer, staff without a
 * merchant profile, or a merchant who is pending, rejected or suspended. Not in a page, a JSON
 * answer, the storefront or the public API.
 */
class PortalVisibilityTest extends WholesaleTestCase
{
    private Product $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->shirt, $variants] = $this->shirt(['wholesale_price' => 43.21]);
        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $this->tier('خاص', 0, default: true)->id, 'price' => 39.87]);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach ($this->portalPages() as $path) {
            $this->get($this->siteUrl($path))->assertRedirect(route('store.account.login'));
        }

        $this->getJson($this->siteUrl('/ar/wholesale/cart'))->assertUnauthorized();
        $this->put($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id), ['quantities' => []])->assertRedirect(route('store.account.login'));
        $this->delete($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id))->assertRedirect(route('store.account.login'));
        $this->post($this->siteUrl('/ar/wholesale/checkout'), $this->checkoutFields())->assertRedirect(route('store.account.login'));
        $this->postJson($this->siteUrl('/ar/wholesale/checkout'), $this->checkoutFields())->assertUnauthorized();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_only_approved_merchants_get_in(): void
    {
        $customer = User::forceCreate(['name' => 'C', 'email' => 'c@example.com', 'password' => 'Secret-pass-123'])->assignRole(UserRole::CUSTOMER->value);
        $staff = $this->staff(['Merchant View List']);

        $outsiders = [
            'customer' => $customer,
            'staff' => $staff,
            'pending' => $this->merchant(MerchantStatus::PENDING)->user,
            'rejected' => $this->merchant(MerchantStatus::REJECTED)->user,
            'suspended' => $suspended = $this->merchant(MerchantStatus::SUSPENDED)->user,
        ];

        // The suspended merchant's own earlier order: not even that is shown now.
        Order::query()->create(['order_number' => 'B2B-SOMEONE', 'user_id' => $suspended->id, 'channel' => OrderChannel::B2B, 'status' => OrderStatus::AWAITING_CONFIRMATION, 'sub_total' => 43.21, 'discount' => 0, 'delivery_fee' => 0, 'total' => 43.21]);

        foreach ($outsiders as $who => $user) {
            $this->flushSession(); // a new browser: `auth.session` ties a session to one account

            foreach ($this->portalPages() as $path) {
                $response = $this->actingAs($user)->get($this->siteUrl($path));
                $response->assertRedirect(route('store.wholesale.status'));
                $this->assertNoPrices((string) $response->getContent(), "{$who} {$path}");
            }

            $json = $this->actingAs($user)->getJson($this->siteUrl('/ar/wholesale/cart'));
            $json->assertForbidden();
            $this->assertNoPrices((string) $json->getContent(), "{$who} json");

            $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id), ['quantities' => []])->assertRedirect(route('store.wholesale.status'));
            $this->actingAs($user)->delete($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id))->assertRedirect(route('store.wholesale.status'));
            $this->actingAs($user)->post($this->siteUrl('/ar/wholesale/checkout'), $this->checkoutFields())->assertRedirect(route('store.wholesale.status'));
            $this->actingAs($user)->postJson($this->siteUrl('/ar/wholesale/checkout'), $this->checkoutFields())->assertForbidden();
            $this->actingAs($user)->deleteJson($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id))->assertForbidden();
            $this->assertSame(1, Order::query()->count(), "{$who} placed an order");

            $status = $this->actingAs($user)->get($this->siteUrl('/ar/wholesale/account'));
            $status->assertOk();
            $this->assertNoPrices((string) $status->getContent(), "{$who} status");
        }
    }

    public function test_a_suspension_takes_effect_on_the_next_request(): void
    {
        $merchant = $this->merchant();

        $this->actingAs($merchant->user)->get($this->siteUrl('/ar/wholesale'))->assertOk();

        $merchant->update(['status' => MerchantStatus::SUSPENDED]);

        $this->actingAs($merchant->user)->get($this->siteUrl('/ar/wholesale'))->assertRedirect(route('store.wholesale.status'));
    }

    public function test_an_approved_merchant_sees_the_prices(): void
    {
        $merchant = $this->merchant();

        $catalog = $this->actingAs($merchant->user)->get($this->siteUrl('/ar/wholesale'));
        $catalog->assertOk();
        $this->assertStringContainsString('39.87', (string) $catalog->getContent());

        $this->actingAs($merchant->user)->getJson($this->siteUrl('/ar/wholesale/cart'))->assertOk()->assertJsonStructure(['groups', 'summary']);
    }

    public function test_the_public_api_has_no_wholesale_or_cost_price(): void
    {
        $this->shirt->update(['cost_price' => 27.65]);

        foreach (['/api/v1/items/products', '/api/v1/items/products/'.$this->shirt->id] as $path) {
            $content = (string) $this->getJson($this->siteUrl($path))->getContent();

            $this->assertNoPrices($content, $path);
            $this->assertStringNotContainsString('cost_price', $content, $path);
            $this->assertStringNotContainsString('27.65', $content, $path);
        }

        // Asking for either column by name is refused (the refusal names it, but holds no price).
        foreach (['wholesale_price' => '43.21', 'cost_price' => '27.65'] as $column => $value) {
            $fields = $this->getJson($this->siteUrl('/api/v1/items/products?fields[products]=id,'.$column));
            $this->assertStringNotContainsString($value, (string) $fields->getContent(), $column);
            $this->assertSame(400, $fields->getStatusCode(), $column);
        }
    }

    public function test_the_store_never_shows_wholesale_prices_or_routes(): void
    {
        $merchant = $this->merchant();

        foreach (['/ar/product/'.$this->shirt->slug, '/ar/shop'] as $path) {
            $content = (string) $this->actingAs($merchant->user)->get($this->siteUrl($path))->getContent();

            $this->assertNoPrices($content, $path);
            // The store links to the portal on purpose (`/{locale}/wholesale`); its routes and prices stay out.
            $this->assertStringNotContainsString('store.wholesale.', $content, $path);
            $this->assertStringNotContainsString('wholesale_price', $content, $path);
        }
    }

    /**
     * @return list<string>
     */
    private function portalPages(): array
    {
        return [
            '/ar/wholesale',
            '/ar/wholesale/product/'.$this->shirt->slug,
            '/ar/wholesale/cart',
            '/ar/wholesale/checkout',
            '/ar/wholesale/checkout/done',
            '/ar/wholesale/orders',
            '/ar/wholesale/orders/B2B-SOMEONE',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function checkoutFields(): array
    {
        return ['address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid()];
    }

    private function assertNoPrices(string $content, string $where): void
    {
        $this->assertStringNotContainsString('43.21', $content, $where);
        $this->assertStringNotContainsString('39.87', $content, $where);
        $this->assertStringNotContainsString('wholesale_price', $content, $where);
    }
}
