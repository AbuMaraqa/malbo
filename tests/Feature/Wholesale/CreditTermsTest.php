<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Http\Requests\OrderRequest;
use Modules\Items\Services\OrderService;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Services\MerchantLedger;

/**
 * Credit terms: a merchant with credit enabled may order on credit while its balance, its open B2B
 * orders (not delivered, not cancelled or rejected) and this order stay within its credit limit,
 * checked on the server under a lock on the merchant row.
 */
class CreditTermsTest extends WholesaleTestCase
{
    private Merchant $merchant;

    private Product $belt;

    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant();
        $this->belt = $this->single(['stock_quantity' => 100]);
        $this->staffUser = $this->staff([]);
    }

    public function test_credit_is_not_offered_without_a_limit(): void
    {
        $this->fill(5);
        $this->checkoutPage()->assertInertia(fn (Assert $page) => $page->has('paymentTerms', 2)->where('credit', null)->etc());
        $this->place()->assertSessionHasErrors('payment_terms');

        $this->merchant->forceFill(['credit_enabled' => true, 'credit_limit' => 1000])->save();
        $this->checkoutPage()->assertInertia(fn (Assert $page) => $page
            ->has('paymentTerms', 3)
            ->where('paymentTerms.2.value', 'credit')
            ->where('credit.limit_label', '1,000.00 ₪')
            ->where('credit.room_label', '1,000.00 ₪')
            ->etc());
    }

    public function test_credit_within_the_limit_places_an_on_account_order(): void
    {
        $this->merchant->forceFill(['credit_enabled' => true, 'credit_limit' => 1000])->save();
        $this->fill(5);

        $this->place()->assertRedirect(route('store.wholesale.checkout.done'))->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame(PaymentMethod::ON_ACCOUNT, $order->payment_method);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
    }

    public function test_credit_counts_balance_and_open_orders(): void
    {
        $this->merchant->forceFill(['credit_enabled' => true, 'credit_limit' => 300])->save();
        app(MerchantLedger::class)->recordAdjustment($this->merchant, ['amount' => 100, 'entry_date' => now()->toDateString(), 'note' => 'رصيد سابق'], $this->staffUser->id);
        $this->order(6); // 120, open

        $this->fill(5); // 100: 100 + 120 + 100 = 320
        $this->place()->assertSessionHasErrors('payment_terms');
        $this->assertStringContainsString('80.00', session('errors')->first('payment_terms'));
        $this->assertSame(1, Order::query()->count());

        $this->fill(4); // 80: exactly the limit
        $this->place()->assertSessionHasNoErrors();
        $this->assertSame(2, Order::query()->count());
    }

    public function test_cancelled_and_delivered_orders_do_not_count_as_open(): void
    {
        $this->merchant->forceFill(['credit_enabled' => true, 'credit_limit' => 200])->save();
        $cancelled = $this->order(7); // 140
        app(OrderService::class)->changeStatus($cancelled, OrderStatus::CANCELLED, $this->staffUser->id);
        $delivered = $this->order(2); // 40, then invoiced: the balance
        foreach ([OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED] as $status) {
            app(OrderService::class)->changeStatus($delivered->fresh(), $status, $this->staffUser->id);
        }

        $this->fill(8); // 160: 40 + 0 + 160 = 200
        $this->place()->assertSessionHasNoErrors();
    }

    public function test_a_suspended_credit_is_refused_even_if_the_page_offered_it(): void
    {
        $this->merchant->forceFill(['credit_enabled' => true, 'credit_limit' => 1000])->save();
        $this->fill(1);
        $this->merchant->forceFill(['credit_enabled' => false])->save();

        $this->place()->assertSessionHasErrors('payment_terms');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_staff_edit_credit_settings(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
        $url = $this->siteUrl('/ar/admin/wholesale/merchants/'.$this->merchant->id);
        $payload = ['shop_name' => 'محل الأناقة', 'owner_name' => 'Owner', 'city_id' => null, 'phone' => '0599111222', 'commercial_register' => 'CR-1001', 'shop_address' => 'شارع', 'tier_id' => null, 'sales_rep_id' => null, 'minimum_order_value' => null, 'minimum_order_pieces' => null];

        $this->actingAs($admin)->from($url.'/edit')->put($url, [...$payload, 'credit_enabled' => true, 'credit_limit' => null])->assertSessionHasErrors('credit_limit');
        $this->actingAs($admin)->from($url.'/edit')->put($url, [...$payload, 'credit_enabled' => true, 'credit_limit' => 2500])->assertSessionHasNoErrors();

        $this->assertTrue($this->merchant->fresh()->credit_enabled);
        $this->assertSame('2500.00', $this->merchant->fresh()->credit_limit);

        $this->actingAs($admin)->get($url.'/edit')->assertInertia(fn (Assert $page) => $page->where('merchant.credit_enabled', true)->where('merchant.credit_limit', '2500.00')->etc());

        // A form without the credit fields leaves them as they are.
        $this->actingAs($admin)->from($url.'/edit')->put($url, $payload)->assertSessionHasNoErrors();
        $this->assertTrue($this->merchant->fresh()->credit_enabled);
    }

    public function test_staff_put_an_order_on_account_only_within_the_merchants_credit(): void
    {
        $order = $this->order(6); // 120, cash
        $onAccount = fn (Order $order, array $more = []) => app(OrderService::class)->update($order->id, [
            'status' => $order->fresh()->status->value,
            'items' => $order->fresh('items')->items->map(fn ($item) => ['id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $item->quantity])->all(),
            'delivery_type' => 1, 'delivery_fee' => 0, 'payment_status' => 2,
            'payment_method' => PaymentMethod::ON_ACCOUNT->value,
            ...$more,
        ], $this->staffUser->id);
        $refused = function (callable $change): void {
            try {
                $change();
                $this->fail('The order went on account.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('payment_method', $e->errors());
            }
        };

        // No credit: refused.
        $refused(fn () => $onAccount($order));

        // Within the limit: the order already counts as open, so only what it grows by needs room.
        $this->merchant->forceFill(['credit_enabled' => true, 'credit_limit' => 150])->save();
        $onAccount($order);
        $this->assertSame(PaymentMethod::ON_ACCOUNT, $order->fresh()->payment_method);
        $refused(fn () => $onAccount($order, ['delivery_fee' => 40])); // 160 > 150
        $onAccount($order, ['delivery_fee' => 30]); // 150

        // A storefront order never goes on account.
        $b2c = app(OrderService::class)->checkout(['user_id' => $this->merchant->user_id, 'customer_name' => 'x', 'contact_phone' => '0599', 'delivery_address' => 'x', 'payment_method' => 1], [['product_id' => $this->belt->id, 'quantity' => 1, 'price' => 20]]);
        $refused(fn () => $onAccount($b2c));
    }

    public function test_credit_needs_its_own_permission(): void
    {
        $this->assertTrue(User::query()->role('Super Admin')->first()?->can('Merchant Credit Update') ?? false);

        $editor = $this->staff(['Merchant View List', 'Merchant Update']);
        $url = $this->siteUrl('/ar/admin/wholesale/merchants/'.$this->merchant->id);
        $payload = ['shop_name' => 'اسم جديد', 'owner_name' => 'Owner', 'city_id' => null, 'phone' => '0599111222', 'commercial_register' => 'CR-1001', 'shop_address' => 'شارع', 'tier_id' => null, 'sales_rep_id' => null, 'minimum_order_value' => null, 'minimum_order_pieces' => null];

        // The profile is saved; the credit fields are ignored.
        $this->actingAs($editor)->from($url.'/edit')->put($url, [...$payload, 'credit_enabled' => true, 'credit_limit' => 99999])->assertSessionHasNoErrors();

        $this->assertSame('اسم جديد', $this->merchant->fresh()->shop_name);
        $this->assertFalse($this->merchant->fresh()->credit_enabled);
        $this->assertNull($this->merchant->fresh()->credit_limit);
    }

    public function test_the_api_refuses_on_account(): void
    {
        $rule = (new OrderRequest)->rules()['payment_method'];

        $this->assertTrue(Validator::make(['payment_method' => PaymentMethod::ON_ACCOUNT->value], ['payment_method' => $rule])->fails());
        $this->assertFalse(Validator::make(['payment_method' => PaymentMethod::CASH->value], ['payment_method' => $rule])->fails());
    }

    private function fill(int $quantity): void
    {
        $this->actingAs($this->merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$this->belt->id), ['quantities' => [$this->belt->id => $quantity]])->assertSessionHasNoErrors();
    }

    private function order(int $quantity): Order
    {
        return app(OrderService::class)->checkout([
            'user_id' => $this->merchant->user_id, 'customer_name' => $this->merchant->shop_name, 'contact_phone' => $this->merchant->phone,
            'delivery_address' => 'x', 'payment_method' => 1, 'delivery_fee' => 0, 'discount' => 0,
        ], [['product_id' => $this->belt->id, 'quantity' => $quantity, 'price' => 20]], OrderChannel::B2B);
    }

    private function checkoutPage(): TestResponse
    {
        return $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/checkout'))->assertOk();
    }

    private function place(): TestResponse
    {
        return $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'credit', 'submit_token' => (string) Str::uuid(),
        ]);
    }
}
