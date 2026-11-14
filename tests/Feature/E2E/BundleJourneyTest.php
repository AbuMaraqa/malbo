<?php

namespace Tests\Feature\E2E;

use Modules\Items\Entities\BundleComponent;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Support\OrderDocument;

/**
 * Sets and bundle offers sold from their pieces' stock: the buyer picks a size per piece, each
 * piece's stock goes down, a cancellation puts each piece back, and the invoice shows the set
 * with its pieces.
 */
class BundleJourneyTest extends E2ETestCase
{
    public function test_a_set_with_chosen_sizes_sells_and_restocks_its_pieces(): void
    {
        $demo = $this->demo();
        $set = $demo->bundles['summer-set'];
        $shirt = $demo->item('linen-shirt', 'white', 'L');
        $chinos = $demo->item('chinos', 'beige', '30');
        $components = BundleComponent::query()->where('bundle_id', $set->id)->orderBy('sort_order')->get();
        $shirtComponent = $components->firstWhere('product_id', $demo->products['linen-shirt']->id);
        $chinosComponent = $components->firstWhere('product_id', $demo->products['chinos']->id);
        [$shirtStock, $chinosStock] = [$this->stock($shirt), $this->stock($chinos)];

        $page = $this->page('/ar/product/'.$set->slug, 'Store/Storefront/BundlePage');
        $this->assertSame((string) $set->translate('ar')->name, $page['product']['name'] ?? $page['bundle']['name'] ?? null);

        // A size for every piece, or nothing goes in the cart.
        unset($this->defaultCookies['store_cart']);
        $this->asGuest()->from($this->siteUrl('/ar/product/'.$set->slug))
            ->post($this->siteUrl('/ar/cart'), ['product_id' => $set->id, 'quantity' => 1, 'components' => [$shirtComponent->id => $shirt->id]])
            ->assertSessionHasErrors('components');

        $cart = $this->addToCart($set, 1, components: [$shirtComponent->id => $shirt->id, $chinosComponent->id => $chinos->id]);
        $this->checkout($cart)->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->assertSame(['279.00'], [$order->sub_total]);
        $this->assertCount(2, $order->items);
        $this->assertSame(1, $order->items->pluck('bundle_group')->unique()->count(), 'one set on the order');
        $this->assertSame([$set->id], $order->items->pluck('bundle_product_id')->unique()->values()->all());
        $this->assertEquals(279, round($order->items->sum(fn ($item) => (float) $item->total_price), 2), 'the pieces share the set price exactly');
        $this->assertSame([$shirtStock - 1, $chinosStock - 1], [$this->stock($shirt), $this->stock($chinos)]);
        $this->assertSame(0, (int) $set->fresh()->stock_quantity, 'the set holds no stock of its own');

        // The invoice heads the pieces with the set.
        $document = OrderDocument::for($order->fresh('items'), 'ar');
        $this->assertSame((string) $set->translate('ar')->name, $document['lines'][0]['bundle']['name']);
        $this->assertSame(2, $document['item_count'], 'two pieces in the parcel');
        $html = view('items::pdf.invoice', ['doc' => $document])->render();
        $this->assertSame(1, substr_count($html, (string) $set->translate('ar')->name));
        $this->assertDocument($this->actingAs($this->admin())->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/invoice')), 'application/pdf');

        // Cancelled: each piece back, once.
        $this->adminStatus($order, OrderStatus::CANCELLED)->assertSessionHasNoErrors();
        $this->adminStatus($order, OrderStatus::CANCELLED)->assertSessionHasNoErrors();
        $this->assertSame([$shirtStock, $chinosStock], [$this->stock($shirt), $this->stock($chinos)]);
        $this->assertSame(['sale' => -2, 'cancel' => 2], $this->movements($order));
    }

    public function test_a_bundle_offer_is_priced_from_its_pieces_and_a_single_piece_cannot_oversell_it(): void
    {
        $demo = $this->demo();
        $bundle = $demo->bundles['office-bundle'];
        $tie = $demo->item('silk-tie');
        $components = BundleComponent::query()->where('bundle_id', $bundle->id)->get()->keyBy('product_id');

        // 15% off 149 + 89 + 119.
        $this->assertSame('303.45', number_format((float) $bundle->fresh()->base_price * 0.85, 2, '.', ''));

        // Two ties left: one bundle and one tie alone take them; a second bundle is refused.
        $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/stock/adjust'), ['product_id' => $tie->id, 'delta' => 2 - $this->stock($tie), 'reason' => 'جرد'])->assertSessionHasNoErrors();
        $choice = fn () => [
            $components[$demo->products['oxford-shirt']->id]->id => $demo->item('oxford-shirt', 'white', 'L')->id,
            $components[$demo->products['silk-tie']->id]->id => $tie->id,
            $components[$demo->products['leather-belt']->id]->id => $demo->item('leather-belt', 'brown')->id,
        ];

        $this->checkout($this->addToCart($bundle, 1, components: $choice()))->assertSessionHasNoErrors();
        $this->checkout($this->addToCart($tie, 1))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->stock($tie));

        unset($this->defaultCookies['store_cart']);
        $this->asGuest()->post($this->siteUrl('/ar/cart'), ['product_id' => $bundle->id, 'quantity' => 1, 'components' => $choice()])->assertSessionHasErrors();
        $this->assertSame(0, $this->stock($tie));
    }
}
