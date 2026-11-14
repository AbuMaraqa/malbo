<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Services\OrderService;
use Modules\Items\Support\OrderDocument;
use Modules\Store\Services\OrderTracking;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesBundles;

/**
 * A set on an order shows as the set with its pieces: on the admin's order page, the invoice and the
 * buyer's tracking page.
 */
class BundleOrderViewsTest extends DatabaseTestCase
{
    use MakesBundles;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3], 120);
        [$trousers, $sizes] = $this->sized('بنطال', ['32' => 3], 180);
        $set = $this->bundle([[$shirt, 1], [$trousers, 1]], ['name' => 'طقم الصيف', 'sku' => 'SET-SUMMER', 'base_price' => 240]);
        $belt = Product::factory()->create(['name' => 'حزام', 'base_price' => 50, 'manage_stock' => false]);

        $this->order = app(OrderService::class)->checkout(
            ['payment_method' => PaymentMethod::CASH->value, 'customer_name' => 'Buyer', 'contact_phone' => '0599123456', 'delivery_fee' => 20],
            [
                ['product_id' => $set->id, 'quantity' => 2, 'price' => 240, 'components' => [['product_id' => $shirts['M']->id, 'quantity' => 1], ['product_id' => $sizes['32']->id, 'quantity' => 1]]],
                ['product_id' => $belt->id, 'quantity' => 1, 'price' => 50],
            ],
        );
    }

    public function test_the_edit_page_groups_set_lines(): void
    {
        $staff = User::forceCreate(['name' => 'Staff', 'email' => 'staff-'.Str::random(10).'@items.test', 'password' => 'secret-password'])->assignRole('Super Admin');

        $this->actingAs($staff)->get($this->siteUrl('/ar/admin/orders/'.$this->order->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Order/Edit')
            ->has('order.items', 3)
            ->where('order.items.0.bundle.name', 'طقم الصيف')
            ->where('order.items.0.bundle.quantity', 2)
            ->where('order.items.0.bundle.key', $this->order->items()->orderBy('id')->first()->bundle_group)
            ->where('order.items.0.name', 'قميص — M')
            ->where('order.items.0.total', '192.00')
            ->where('order.items.2.bundle', null));
    }

    public function test_the_invoice_prints_the_set_and_its_pieces(): void
    {
        $document = OrderDocument::for($this->order, 'ar');
        [$shirt, $trousers, $belt] = $document['lines'];

        $this->assertSame(['name' => 'طقم الصيف', 'sku' => 'SET-SUMMER', 'quantity' => 2, 'unit_price' => '240.00 ₪', 'total' => '480.00 ₪', 'first' => true], array_diff_key($shirt['bundle'], ['key' => true]));
        $this->assertFalse($trousers['bundle']['first']);
        $this->assertNull($belt['bundle']);
        $this->assertSame(5, $document['item_count'], 'pieces in the parcel');

        $html = view('items::pdf.invoice', ['doc' => $document])->render();
        $this->assertStringContainsString('طقم الصيف', $html);
        $this->assertStringContainsString('SET-SUMMER', $html);
        $this->assertSame(1, substr_count($html, 'طقم الصيف'), 'the set heads its pieces once');
    }

    public function test_tracking_names_the_set(): void
    {
        $lines = app(OrderTracking::class)->result($this->order->fresh(['items', 'statusHistory', 'shipment']))['lines'];

        $this->assertSame(['طقم الصيف ×2', 'طقم الصيف ×2', null], array_column($lines, 'bundle'));
    }
}
