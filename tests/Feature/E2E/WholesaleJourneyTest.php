<?php

namespace Tests\Feature\E2E;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Services\WholesaleCartService;

/**
 * A shop joins the wholesale portal: waits for approval, sees its tier's prices (the storefront
 * never does), orders by the size run, is invoiced on delivery and pays, orders on credit,
 * reserves incoming goods, reorders and shares the PDF catalog.
 */
class WholesaleJourneyTest extends E2ETestCase
{
    public function test_a_merchant_from_application_to_reorder(): void
    {
        $demo = $this->demo();
        $oxford = $demo->products['oxford-shirt'];
        $white = fn (string $size) => $demo->item('oxford-shirt', 'white', $size);

        // The join request: a pending account that sees no prices and cannot order.
        $this->page('/ar/wholesale/apply', 'Wholesale/Portal/Apply');
        $this->post($this->siteUrl('/ar/wholesale/apply'), [
            'shop_name' => 'محل الشباب', 'owner_name' => 'وسيم', 'city_id' => $demo->cities['north']->id, 'phone' => '0599 777 888',
            'email' => 'shop@example.com', 'commercial_register' => 'CR-9001', 'shop_address' => 'شارع فلسطين',
            'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertSessionHasNoErrors();
        $merchant = Merchant::query()->where('email', 'shop@example.com')->sole();
        $user = $merchant->user;
        $this->assertSame(MerchantStatus::PENDING, $merchant->status);

        $this->actingAs($user)->get($this->siteUrl('/ar/wholesale'))->assertRedirect(route('store.wholesale.status'));
        $this->actingAs($user)->get($this->siteUrl('/ar/wholesale/product/'.$oxford->slug))->assertRedirect(route('store.wholesale.status'));
        $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$oxford->id), ['quantities' => [$white('M')->id => 2]])->assertRedirect(route('store.wholesale.status'));
        $status = $this->actingAs($user)->get($this->siteUrl('/ar/wholesale/account'))->assertOk()->getContent();
        $this->assertStringNotContainsString('80.00', $status);
        $this->assertStringNotContainsString('72.00', $status);

        // Staff approve it on the gold tier.
        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/wholesale/merchants/'.$merchant->id.'/status'), ['status' => 'approved', 'note' => null])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/wholesale/merchants/'.$merchant->id), [
            'shop_name' => $merchant->shop_name, 'owner_name' => $merchant->owner_name, 'city_id' => $merchant->city_id, 'phone' => $merchant->phone,
            'commercial_register' => $merchant->commercial_register, 'shop_address' => $merchant->shop_address,
            'tier_id' => $demo->extra['tiers']['gold']->id, 'sales_rep_id' => $this->admin()->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(MerchantStatus::APPROVED, $merchant->fresh()->status);

        // The portal shows the gold price (80 less 10%) next to the suggested retail price.
        $this->page('/ar/wholesale', 'Wholesale/Portal/Catalog', $user);
        $product = $this->page('/ar/wholesale/product/'.$oxford->slug, 'Wholesale/Portal/Product', $user);
        $this->assertStringContainsString('72.00', json_encode($product, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('149.00', json_encode($product, JSON_UNESCAPED_UNICODE));

        // The storefront never carries a wholesale price, signed in as the merchant or not.
        foreach ([null, $user] as $viewer) {
            $this->app['auth']->forgetGuards();
            $page = json_encode($this->page('/ar/product/'.$oxford->slug, 'Store/Storefront/ProductPage', $viewer), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('72.00', $page);
            $this->assertStringNotContainsString('80.00', $page);
            $this->assertStringNotContainsString('wholesale_price', $page);
        }

        // A size run in the cart, then cash on delivery.
        $stock = array_map(fn (string $size) => $this->stock($white($size)), ['S' => 'S', 'M' => 'M', 'L' => 'L']);
        $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$oxford->id), ['quantities' => [$white('S')->id => 2, $white('M')->id => 3, $white('L')->id => 3]])->assertSessionHasNoErrors();
        $this->page('/ar/wholesale/cart', 'Wholesale/Portal/Cart', $user);
        $this->page('/ar/wholesale/checkout', 'Wholesale/Portal/Checkout', $user);
        $this->placeWholesale($user, 'cash_on_delivery')->assertSessionHasNoErrors()->assertRedirect(route('store.wholesale.checkout.done'));

        $order = $this->lastOrder();
        $this->assertSame([OrderChannel::B2B, OrderStatus::AWAITING_CONFIRMATION, $user->id], [$order->channel, $order->status, $order->user_id]);
        $this->assertSame(['72.00'], $order->items->pluck('price')->unique()->values()->all());
        $this->assertSame('576.00', $order->total);
        $this->assertSame(['S' => $stock['S'] - 2, 'M' => $stock['M'] - 3, 'L' => $stock['L'] - 3], array_map(fn (string $size) => $this->stock($white($size)), ['S' => 'S', 'M' => 'M', 'L' => 'L']));
        $this->assertSame(1, $this->page('/ar/wholesale/orders', 'Wholesale/Portal/Orders', $user)['orders']['meta']['total'] ?? count($this->page('/ar/wholesale/orders', 'Wholesale/Portal/Orders', $user)['orders']['data']));

        // Delivered: invoiced on the merchant's account; a bank transfer pays half.
        $ledger = app(LedgerEntryRepositoryInterface::class);
        $this->assertSame(0.0, $ledger->balance($merchant->id));
        $this->deliverInAdmin($order);
        $this->assertSame(576.0, $ledger->balance($merchant->id));

        $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/wholesale/merchants/'.$merchant->id.'/payments'), [
            'amount' => 300, 'method' => 'bank_transfer', 'reference' => 'TRX-1', 'note' => null, 'entry_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(276.0, $ledger->balance($merchant->id));
        $this->page('/ar/wholesale/account/statement', 'Wholesale/Portal/Statement', $user);
        $this->assertDocument($this->actingAs($user)->get($this->siteUrl('/ar/wholesale/account/statement/pdf')), 'application/pdf');

        // On credit once staff allow it, within the limit.
        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/wholesale/merchants/'.$merchant->id), [
            'shop_name' => $merchant->shop_name, 'owner_name' => $merchant->owner_name, 'city_id' => $merchant->city_id, 'phone' => $merchant->phone,
            'commercial_register' => $merchant->commercial_register, 'shop_address' => $merchant->shop_address, 'tier_id' => $demo->extra['tiers']['gold']->id,
            'credit_enabled' => true, 'credit_limit' => 1000,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$demo->products['polo']->id), ['quantities' => [$demo->item('polo', 'navy', 'M')->id => 4]])->assertSessionHasNoErrors();
        $this->placeWholesale($user, 'credit')->assertSessionHasNoErrors();
        $this->assertSame(PaymentMethod::ON_ACCOUNT, $this->lastOrder()->payment_method);

        $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$demo->products['navy-suit']->id), ['quantities' => [$demo->item('navy-suit', 'navy', '50')->id => 2]])->assertSessionHasNoErrors();
        $this->placeWholesale($user, 'credit')->assertSessionHasErrors('payment_terms'); // 276 + 180 + 936 > 1000

        // Scarves are sold out but 40 are coming: the merchant reserves 10; the storefront still sells none.
        $scarf = $demo->products['wool-scarf'];
        $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$demo->products['navy-suit']->id), ['quantities' => []]);
        $this->actingAs($user)->delete($this->siteUrl('/ar/wholesale/cart/'.$demo->products['navy-suit']->id));
        $this->actingAs($user)->put($this->siteUrl('/ar/wholesale/cart/'.$scarf->id), ['quantities' => [$scarf->id => 10]])->assertSessionHasNoErrors();
        $this->placeWholesale($user, 'cash_on_delivery')->assertSessionHasNoErrors();
        $preorder = $this->lastOrder();
        $this->assertSame(10, (int) $preorder->items->sole()->preorder_quantity);
        $this->assertSame(-10, $this->stock($scarf));
        $this->app['auth']->forgetGuards();
        unset($this->defaultCookies['store_cart']);
        $this->post($this->siteUrl('/ar/cart'), ['product_id' => $scarf->id, 'quantity' => 1])->assertSessionHasErrors();

        // Reorder fills the cart with the first order's sizes at today's prices.
        $this->actingAs($user)->post($this->siteUrl('/ar/wholesale/orders/'.$order->order_number.'/reorder'))->assertRedirect(route('store.wholesale.cart'));
        $quantities = WholesaleCartService::quantities(app(WholesaleCartService::class)->current($user->id));
        $this->assertSame([$white('S')->id => 2, $white('M')->id => 3, $white('L')->id => 3], array_intersect_key($quantities, array_flip([$white('S')->id, $white('M')->id, $white('L')->id])));
        $this->assertSame(0, Order::query()->where('user_id', $user->id)->where('id', '>', $preorder->id)->count(), 'a reorder only fills the cart');

        // The PDF catalog, downloaded and shared by a signed link.
        $this->page('/ar/wholesale/catalog-pdf', 'Wholesale/Portal/CatalogPdf', $user);
        $this->assertDocument($this->actingAs($user)->get($this->siteUrl('/ar/wholesale/catalog-pdf/download')), 'application/pdf');
        $this->actingAs($user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), ['prices' => 'wholesale'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $this->assertDocument($this->get(CatalogShare::query()->where('merchant_id', $merchant->id)->sole()->url()), 'application/pdf');
    }

    private function placeWholesale(User $user, string $terms): TestResponse
    {
        return $this->actingAs($user)->from($this->siteUrl('/ar/wholesale/checkout'))->post($this->siteUrl('/ar/wholesale/checkout'), [
            'address_source' => 'shop', 'address_id' => null, 'notes' => null, 'payment_terms' => $terms, 'submit_token' => (string) Str::uuid(),
        ]);
    }
}
