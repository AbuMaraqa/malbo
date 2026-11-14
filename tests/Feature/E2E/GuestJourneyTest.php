<?php

namespace Tests\Feature\E2E;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Store\Support\CartCookie;

/**
 * A guest buys on the storefront and MALBO delivers: from the home page to the delivered order,
 * its tracking page and its invoice.
 */
class GuestJourneyTest extends E2ETestCase
{
    public function test_a_guest_buys_with_cash_on_delivery_and_staff_deliver(): void
    {
        $demo = $this->demo();
        $shirt = $demo->item('oxford-shirt', 'sky', 'L');
        $tie = $demo->item('silk-tie');
        $shirtStock = $this->stock($shirt);
        $tieStock = $this->stock($tie);

        // Home, then the search box's suggestions while typing, then the shop filtered by colour and size.
        $this->page('/ar', 'Store/Storefront/Home');
        $suggest = $this->getJson($this->siteUrl('/ar/search/suggest?q='.rawurlencode('أكسفورد')))->assertOk()->json('products');
        $this->assertContains('قميص أكسفورد كلاسيكي', array_column($suggest, 'name'));

        $color = $demo->colors['sky'];
        $size = $demo->sizes['L'];
        $shop = $this->page('/ar/shop?attr['.$color->attribute_id.']='.$color->id.'&attr['.$size->attribute_id.']='.$size->id, 'Store/Storefront/Shop');
        $found = array_column($shop['products']['data'], 'id');
        $this->assertContains($demo->products['oxford-shirt']->id, $found, 'a light blue shirt in L');
        $this->assertNotContains($demo->products['chinos']->id, $found, 'chinos come in neither');
        $searched = array_column($this->page('/ar/shop?q='.rawurlencode('Oxford'), 'Store/Storefront/Shop')['products']['data'], 'id');
        $this->assertContains($demo->products['oxford-shirt']->id, $searched, 'search finds English names too');

        // The product page, then the cart.
        $product = $this->page('/ar/product/'.$demo->products['oxford-shirt']->slug, 'Store/Storefront/ProductPage');
        $this->assertSame('قميص أكسفورد كلاسيكي', $product['product']['name']);

        $cart = $this->addToCart($shirt, 2);
        $this->addToCart($tie, 1, $cart);
        $this->assertCount(2, $this->page('/ar/cart', 'Store/Storefront/Cart', cart: $cart)['lines']);

        // Delivery by zone: the centre is free from 300, the north from 400 (fee 30). The coupon is 10%.
        $center = $this->quote($cart, $demo->cities['center']->id, $demo->coupon);
        $this->assertEquals([387, 38.7, 0, 348.3], [$center['subtotal'], $center['discount'], $center['delivery_fee'], $center['total']]);
        $this->assertTrue($center['coupon']['valid']);
        $north = $this->quote($cart, $demo->cities['north']->id, $demo->coupon);
        $this->assertEquals([30, 378.3], [$north['delivery_fee'], $north['total']]);

        $small = $this->addToCart($tie, 1);
        $tieOnly = $this->quote($small, $demo->cities['center']->id, null);
        $this->assertEquals([89, 20, 109], [$tieOnly['subtotal'], $tieOnly['delivery_fee'], $tieOnly['total']], 'below the free-delivery minimum');
        $this->assertFalse($this->quote($small, $demo->cities['center']->id, $demo->coupon)['coupon']['valid'], 'the coupon needs 100');

        // Cash on delivery: the order waits for MALBO, its stock is taken now.
        $this->checkout($cart, ['coupon' => strtolower($demo->coupon), 'phone' => '0599 123 123', 'name' => 'ضيف'])
            ->assertSessionHasNoErrors()->assertRedirect(route('store.checkout.done'));
        $order = $this->lastOrder();

        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
        $this->assertSame(OrderChannel::B2C, $order->channel);
        $this->assertSame(PaymentMethod::CASH, $order->payment_method);
        $this->assertNull($order->user_id);
        $this->assertSame(['387.00', '38.70', '0.00', '348.30', $demo->coupon], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total, $order->coupon_code]);

        $line = $order->items->firstWhere('product_id', $shirt->id);
        $this->assertSame($shirt->sku, $line->sku);
        $this->assertSame(['ar' => 'قميص أكسفورد كلاسيكي', 'en' => 'Classic Oxford shirt', 'he' => 'חולצת אוקספורד קלאסית'], $line->product_name);
        $this->assertEqualsCanonicalizing(['Light blue', 'L'], array_map(fn (array $option) => $option['value']['en'], $line->options));
        $this->assertSame('149.00', $line->price);

        $this->assertSame($shirtStock - 2, $this->stock($shirt));
        $this->assertSame($tieStock - 1, $this->stock($tie));
        $this->assertSame(['sale' => -3], $this->movements($order));

        $done = $this->page('/ar/checkout/done', 'Store/Storefront/CheckoutDone', cart: $cart);
        $this->assertSame($order->order_number, $done['order']['number']);

        // Tracking without an account: number and phone, typed any way.
        $this->assertSame('awaiting_confirmation', $this->track($order->order_number, '+970599123123')['status']['key']);
        $this->assertNull($this->track($order->order_number, '0599000000'), 'another phone sees nothing');

        // Staff confirm, prepare, ship and deliver.
        $this->deliverInAdmin($order);
        $shipment = Shipment::query()->where('order_id', $order->id)->sole();
        $this->assertSame('delivered', $shipment->status->value);
        $this->assertNotNull($shipment->delivered_at);

        $tracked = $this->track($order->order_number, '0599123123');
        $this->assertSame('delivered', $tracked['status']['key']);
        $this->assertSame([true, true, true, true, true], array_column($tracked['timeline'], 'reached'));

        // The stock moved once, at checkout.
        $this->assertSame($shirtStock - 2, $this->stock($shirt));
        $this->assertSame(['sale' => -3], $this->movements($order));

        // The papers.
        $this->assertDocument($this->actingAs($this->admin())->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/invoice')), 'application/pdf');
        $this->assertDocument($this->actingAs($this->admin())->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/waybill')), 'application/pdf');
        $this->assertDocument($this->actingAs($this->admin())->get($this->siteUrl('/en/admin/orders/'.$order->id.'/invoice?locale=he')), 'application/pdf');

        // The admin's order page shows it delivered with its history.
        $this->actingAs($this->admin())->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Items/Order/Edit')->where('order.status', OrderStatus::DELIVERED->value)->etc());
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $cart, int $cityId, ?string $coupon): array
    {
        return $this->page('/ar/checkout?city_id='.$cityId.($coupon ? '&coupon='.$coupon : ''), 'Store/Storefront/Checkout', cart: $cart)['quote'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function track(string $number, string $phone): ?array
    {
        $result = null;
        $response = $this->from($this->siteUrl('/ar/track'))->post($this->siteUrl('/ar/track'), ['number' => $number, 'phone' => $phone]);

        if ($response->isRedirect()) {
            $response->assertSessionHasErrors(); // "no order with this number and phone"

            return null;
        }

        $response->assertOk()
            ->assertInertia(function (Assert $page) use (&$result) {
                $result = $page->toArray()['props']['result'];
                $page->etc();
            });

        return $result;
    }
}
