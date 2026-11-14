<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Items\Support\BundleStock;
use Modules\Store\Entities\Cart;
use Modules\Store\Services\ProductConfigurator;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Items\Concerns\MakesBundles;

/**
 * The review of phase 8B: bundle edits keep carts, shared stock between pieces, bundles kept out of
 * the product admin and the app API, exchanges of set pieces, percent prices that follow the pieces.
 */
class BundleReviewFixesTest extends StorefrontTestCase
{
    use MakesBundles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password', 'email_verified_at' => now()])
            ->assignRole('Super Admin');
    }

    public function test_a_cart_line_survives_a_bundle_edit_and_a_removed_piece_invalidates_it(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3], 120);
        $belt = Product::factory()->create(['name' => 'حزام', 'manage_stock' => false]);
        $set = $this->bundle([[$shirt, 1], [$belt, 1]], ['slug' => 'set-edit']);
        [$shirtComponent, $beltComponent] = $set->bundleComponents()->get()->all();

        $token = (string) $this->post($this->siteUrl('/ar/cart'), ['product_id' => $set->id, 'quantity' => 1, 'components' => [$shirtComponent->id => $shirts['M']->id]])
            ->assertSessionHasNoErrors()->getCookie(CartCookie::NAME)?->getValue();

        $edit = fn (array $components, array $more = []) => $this->actingAs($this->admin)->post($this->siteUrl('/ar/admin/bundles/'.$set->id), [
            '_method' => 'put', 'bundle_type' => 'set', 'name' => 'طقم جديد', 'slug' => 'set-edit', 'status' => ProductStatus::PUBLISHED->value,
            'is_active' => true, 'is_featured' => true, 'pricing' => 'fixed', 'base_price' => 300, 'components' => $components, ...$more,
        ])->assertSessionHasNoErrors();

        $edit([['id' => $shirtComponent->id, 'product_id' => $shirt->id, 'quantity' => 1], ['id' => $beltComponent->id, 'product_id' => $belt->id, 'quantity' => 1]]);

        $this->assertSame([$shirtComponent->id, $beltComponent->id], $set->bundleComponents()->get()->modelKeys(), 'the pieces keep their ids');
        $line = $this->withCookie(CartCookie::NAME, $token)->props('/ar/cart', 'Store/Storefront/Cart')['lines'][0];
        $this->assertTrue($line['available']);
        $this->assertSame('طقم جديد', $line['name']);

        $edit([['id' => $shirtComponent->id, 'product_id' => $shirt->id, 'quantity' => 1]]);

        $this->assertSame([$shirtComponent->id], $set->bundleComponents()->get()->modelKeys());
        $line = $this->withCookie(CartCookie::NAME, $token)->props('/ar/cart', 'Store/Storefront/Cart')['lines'][0];
        $this->assertFalse($line['available'], 'a piece removed: the saved choice no longer fits the set');
    }

    public function test_two_pieces_of_one_product_share_its_stock(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['S' => 1, 'M' => 0], 120);
        $twoShirts = $this->load($this->bundle([[$shirt, 1], [$shirt, 1]]));

        $this->assertSame(0, BundleStock::available($twoShirts), 'one shirt cannot fill two pieces');
        $this->assertFalse(ProductConfigurator::available($twoShirts));

        $shirts['M']->update(['stock_quantity' => 1, 'stock_status' => 'in_stock']);
        $this->assertSame(1, BundleStock::available($this->load($twoShirts)), 'S for one piece, M for the other');

        $shirts['M']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $shirts['S']->update(['stock_quantity' => 3]);
        $this->assertSame(1, BundleStock::available($this->load($twoShirts)), 'three of S make one set of two');

        $pinned = $this->load($this->bundle([[$shirts['S'], 2], [$shirts['S'], 2]]));
        $this->assertSame(0, BundleStock::available($pinned), 'the same variant twice: four needed, three there');

        $belt = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 3]);
        $this->assertSame(1, BundleStock::available($this->load($this->bundle([[$belt, 1], [$belt, 2]]))), 'a product without variants twice');

        $card = collect($this->props('/ar/shop', 'Store/Storefront/Shop')['products']['data'])->firstWhere('id', $pinned->id);
        $this->assertContains('out_of_stock', array_column($card['badges'], 'key'));
    }

    public function test_a_large_family_gets_a_bound_a_buyer_can_reach(): void
    {
        // Five pieces of one shirt in six sizes: 7,776 combinations, past the exact search.
        [$shirt, $shirts] = $this->sized('قميص', ['XS' => 1, 'S' => 1, 'M' => 1, 'L' => 1, 'XL' => 1, 'XXL' => 0], 120);
        $five = $this->bundle(array_fill(0, 5, [$shirt, 1]));

        $this->assertSame(1, BundleStock::available($this->load($five)), 'a different size for each piece');

        $shirts['XS']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->assertSame(0, BundleStock::available($this->load($five)), 'four shirts left for five pieces');

        $shirts['M']->update(['stock_quantity' => 9]);
        $this->assertSame(1, BundleStock::available($this->load($five)), 'never more than the stock allows: 12 shirts, 5 per set, but M holds 9 of them');
    }

    public function test_the_product_admin_refuses_bundles(): void
    {
        [$shirt] = $this->sized('قميص', ['M' => 3]);
        $set = $this->bundle([[$shirt, 1]], ['wholesale_price' => 50]);
        $admin = fn () => $this->actingAs($this->admin);
        $url = fn (string $path) => $this->siteUrl('/ar/admin'.$path);

        $admin()->put($url('/products/'.$set->id), ['name' => 'x'])->assertNotFound();
        $admin()->put($url('/products/'.$set->id.'/status'), ['status' => ProductStatus::DRAFT->value])->assertNotFound();
        $admin()->put($url('/products/'.$set->id.'/active'), ['active' => false])->assertNotFound();
        $admin()->put($url('/products/'.$set->id.'/look'), ['products' => []])->assertNotFound();
        $admin()->getJson($url('/products/'.$set->id.'/barcodes'))->assertNotFound();
        $admin()->get($url('/products/'.$set->id.'/branch-pricings'))->assertNotFound();
        $admin()->put($url('/products/'.$set->id.'/branch-pricings'), ['prices' => [$set->id => []]])->assertNotFound();
        $admin()->get($url('/wholesale/prices/'.$set->id.'/edit'))->assertNotFound();
        $admin()->delete($url('/products/'.$set->id))->assertNotFound();
        $admin()->delete($url('/products'), ['ids' => [$set->id]]);

        $fresh = $set->fresh();
        $this->assertNotNull($fresh, 'bulk delete leaves a set alone');
        $this->assertSame(ProductStatus::PUBLISHED, $fresh->status);
        $this->assertSame(1, (int) $fresh->is_active);
    }

    public function test_the_app_api_lists_no_bundles(): void
    {
        [$shirt] = $this->sized('قميص', ['M' => 3]);
        $set = $this->bundle([[$shirt, 1]]);

        $listed = collect($this->getJson($this->siteUrl('/api/v1/items/products?per_page=100'))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($listed->contains($shirt->id));
        $this->assertFalse($listed->contains($set->id));
        $this->getJson($this->siteUrl('/api/v1/items/products/'.$set->id))->assertNotFound();

        $this->admin->givePermissionTo(['Product View List', 'Category View List']);
        $synced = collect($this->actingAs($this->admin, 'sanctum')->getJson($this->siteUrl('/api/v1/items/sync/products?since=2021-01-01 00:00:00'))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($synced->contains($shirt->id));
        $this->assertFalse($synced->contains($set->id));
    }

    public function test_a_set_piece_not_sold_alone_can_be_exchanged(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3, 'L' => 2], 120, ['status' => ProductStatus::DRAFT]);
        $set = $this->bundle([[$shirt, 1]], ['base_price' => 100]);
        $order = app(OrderService::class)->checkout(
            ['payment_method' => PaymentMethod::CASH->value, 'customer_name' => 'Buyer', 'contact_phone' => '0599123456'],
            [['product_id' => $set->id, 'quantity' => 1, 'price' => 100, 'components' => [['product_id' => $shirts['M']->id, 'quantity' => 1]]]],
        );
        $buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'b-'.Str::random(6).'@store.test', 'password' => 'secret-password']);
        $order->forceFill(['status' => OrderStatus::DELIVERED, 'user_id' => $buyer->id])->save();
        $piece = $order->items()->sole();
        $returns = app(ReturnRequestService::class);

        $this->assertSame([$shirts['M']->id, $shirts['L']->id], array_column($returns->exchangeOptions($piece), 'value'));

        $request = $returns->create(Order::query()->findOrFail($order->id), ['type' => 'exchange', 'reason' => 'wrong_size', 'lines' => [['order_item_id' => $piece->id, 'quantity' => 1, 'exchange_product_id' => $shirts['L']->id]]], customer: $buyer);
        $this->assertSame($shirts['L']->id, $request->lines->sole()->exchange_product_id);

        $shirts['L']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->assertSame([$shirts['M']->id], array_column($returns->exchangeOptions($piece), 'value'), 'still only what is in stock');
    }

    public function test_percent_bundles_follow_deleted_and_new_variants(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3, 'L' => 3], 120);
        $shirts['L']->update(['base_price' => 130]);
        $set = $this->bundle([[$shirt, 1]], ['bundle_discount_percent' => 10, 'discount_type' => 'percentage', 'discount_value' => 10, 'base_price' => 120]);

        $shirts['M']->delete();
        $this->assertSame('130.00', $set->fresh()->base_price, 'the cheapest size is gone');

        Product::factory()->variationOf($shirt)->create(['base_price' => 90, 'manage_stock' => false]);
        $this->assertSame('90.00', $set->fresh()->base_price, 'a new, cheaper size');

        $shirts['M']->restore();
        $this->assertSame('90.00', $set->fresh()->base_price);
    }

    public function test_percent_bundles_follow_a_hidden_value(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3, 'L' => 3], 120);
        $shirts['L']->update(['base_price' => 130]);
        $set = $this->bundle([[$shirt, 1]], ['bundle_discount_percent' => 10, 'discount_type' => 'percentage', 'discount_value' => 10, 'base_price' => 120]);

        $shirts['M']->attributeValues()->first()->update(['is_active' => false]);

        $this->assertSame('130.00', $set->fresh()->base_price, 'size M is hidden: the cheapest size a buyer can pick is L');
    }

    private function load(Product $bundle): Product
    {
        return Product::query()->with(BundleStock::relations())->findOrFail($bundle->id);
    }
}
