<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\QuantityBreak;

/**
 * Staff editing a wholesale order in the admin: a new or changed line is priced at the merchant's
 * wholesale price (its tier, the quantity break over the order's pieces of that product), never at
 * retail. A storefront order is still priced at retail.
 */
class B2BOrderEditTest extends WholesaleTestCase
{
    private Merchant $merchant;

    private Product $shirt;

    /** @var array<string, Product> */
    private array $variants;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant(attributes: ['tier_id' => $this->tier('ذهبي', 10)->id, 'city_id' => $this->city('الخليل')->id]);
        [$this->shirt, $this->variants] = $this->shirt();
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_staff_adding_a_size_to_a_b2b_order_price_it_at_wholesale(): void
    {
        QuantityBreak::query()->create(['product_id' => $this->shirt->id, 'min_quantity' => 5, 'discount_percent' => 10]);
        $order = $this->placeB2B(['black-S' => 2]);
        $kept = $order->items->sole();
        $this->assertSame('45.00', $kept->price); // 50 - 10 % (tier)

        $this->update($order, [
            ['id' => $kept->id, 'product_id' => $this->variants['black-S']->id, 'quantity' => 2],
            ['product_id' => $this->variants['white-M']->id, 'quantity' => 3],
        ])->assertSessionHasNoErrors();

        $order->refresh()->load('items');
        $added = $order->items->firstWhere('product_id', $this->variants['white-M']->id);
        // 5 pieces of the shirt reach the break: 45 - 10 % = 40.50, not the retail 100.
        $this->assertSame('40.50', $added->price);
        $this->assertSame('45.00', $order->items->firstWhere('id', $kept->id)->price);
    }

    public function test_a_b2c_order_is_still_priced_at_retail(): void
    {
        $order = Order::query()->create([
            'order_number' => 'B2C-EDIT', 'status' => OrderStatus::PENDING, 'city_id' => $this->merchant->city_id,
            'sub_total' => 0, 'discount' => 0, 'delivery_fee' => 0, 'total' => 0, 'payment_method' => 1, 'payment_status' => 1,
            'delivery_address' => 'x', 'contact_phone' => '0599000000',
        ]);

        $this->update($order, [['product_id' => $this->variants['white-M']->id, 'quantity' => 1]])->assertSessionHasNoErrors();

        $this->assertSame('100.00', $order->refresh()->items()->sole()->price);
    }

    /**
     * @param  array<string, int>  $quantities
     */
    private function placeB2B(array $quantities): Order
    {
        $byId = [];
        foreach ($quantities as $key => $quantity) {
            $byId[$this->variants[$key]->id] = $quantity;
        }

        $this->actingAs($this->merchant->user)->put($this->siteUrl('/ar/wholesale/cart/'.$this->shirt->id), ['quantities' => $byId])->assertSessionHasNoErrors();
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'payment_terms' => 'cash_on_delivery', 'submit_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return Order::query()->with('items')->latest('id')->firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function update(Order $order, array $items)
    {
        $this->flushSession();

        return $this->actingAs($this->admin)->put($this->siteUrl('/ar/admin/orders/'.$order->id), [
            'user_id' => $order->user_id,
            'city_id' => $order->city_id ?? $this->merchant->city_id,
            'items' => $items,
            'delivery_fee' => 0,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method?->value ?? 1,
            'payment_status' => $order->payment_status?->value ?? 1,
            'delivery_type' => 1,
            'delivery_address' => $order->delivery_address ?? 'x',
            'contact_phone' => $order->contact_phone ?? '0599000000',
        ]);
    }
}
