<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ShipmentService;
use Modules\Items\Support\OrderDocument;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * The papers an order is printed on: an A4 invoice in the buyer's language, and a 100 x 150 mm
 * waybill for the courier, one or many in a PDF.
 */
class OrderDocumentsTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();
    }

    public function test_invoice_is_an_a4_pdf_with_the_order_number(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/invoice'))->assertOk();

        $pdf = $this->assertPdf($response, $order->order_number);
        $this->assertStringContainsString('invoice-'.$order->order_number.'.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(1, preg_match_all('#/Type /Page\b(?!s)#', $pdf));
        $this->assertMatchesRegularExpression('#/MediaBox \[0 0 595\.\d+ 841\.\d+\]#', $pdf, 'A4');
        $this->assertStringContainsString('DejaVuSans', $pdf, 'the embedded font that can draw Arabic and Hebrew');
    }

    public function test_invoice_speaks_the_requested_language(): void
    {
        $order = $this->order();

        foreach (['ar' => 'فاتورة', 'en' => 'Invoice', 'he' => 'חשבונית'] as $locale => $title) {
            $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/invoice?locale='.$locale))->assertOk();

            $this->assertStringContainsString($this->utf16($title), (string) $response->getContent(), $locale);
        }

        $this->assertSame('ar', app()->getLocale(), 'the request locale is restored after the render');
    }

    public function test_an_unknown_locale_falls_back_to_arabic(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/en/admin/orders/'.$order->id.'/invoice?locale=xx'))->assertOk();

        $this->assertStringContainsString($this->utf16('فاتورة'), (string) $response->getContent());
    }

    public function test_the_document_data(): void
    {
        [, $variant] = $this->shirtWithVariant();
        $order = $this->order([['product_id' => $variant->id, 'quantity' => 2]], ['delivery_fee' => 20, 'customer_name' => 'Ahmad']);

        $ar = OrderDocument::for($order, 'ar');
        $en = OrderDocument::for($order, 'en');

        $this->assertSame($order->order_number, $ar['number']);
        $this->assertSame(['Ahmad', '0599000000', 'عنوان', 'مدينة', 'حي'], [$ar['customer']['name'], $ar['customer']['phone'], $ar['customer']['address'], $ar['customer']['city'], $ar['customer']['district']]);
        $this->assertSame('قميص', $ar['lines'][0]['name']);
        $this->assertSame('Shirt', $en['lines'][0]['name']);
        $this->assertSame('Color: Black · Size: L', $en['lines'][0]['options']);
        $this->assertSame(['SHIRT-BLK-L', 2, '100.00 ₪', '200.00 ₪'], [$en['lines'][0]['sku'], $en['lines'][0]['quantity'], $en['lines'][0]['unit_price'], $en['lines'][0]['total']]);
        $this->assertSame(['200.00 ₪', null, '20.00 ₪', '220.00 ₪'], [$en['sub_total'], $en['discount'], $en['delivery_fee'], $en['total']]);
        $this->assertSame(['Cash on delivery', 'Unpaid', 'New'], [$en['payment_method'], $en['payment_status'], $en['status']]);
        $this->assertSame(2, $en['item_count']);
        $this->assertSame('rtl', $ar['dir']);
        $this->assertSame('ltr', $en['dir']);
        $this->assertNull($en['courier']);
    }

    public function test_cash_to_collect_only_for_unpaid_cash_orders(): void
    {
        $unpaid = $this->order();
        $paid = $this->order(overrides: ['payment_status' => PaymentStatus::PAID->value]);
        $card = $this->order(overrides: ['payment_method' => 2]);

        $this->assertSame('100.00 ₪', OrderDocument::for($unpaid, 'en')['collect']);
        $this->assertNull(OrderDocument::for($paid, 'en')['collect']);
        $this->assertNull(OrderDocument::for($card, 'en')['collect']);
    }

    public function test_the_waybill_names_the_courier_and_tracking_number(): void
    {
        $order = $this->order();
        foreach ([OrderStatus::CONFIRMED, OrderStatus::PREPARING] as $status) {
            app(OrderService::class)->changeStatus($order, $status, $this->staff->id);
        }
        app(ShipmentService::class)->create($order, ['courier' => 'manual', 'tracking_number' => 'TRK-77'], $this->staff->id);

        $document = OrderDocument::for($order->fresh(), 'en');

        $this->assertSame(['Own delivery', 'TRK-77'], [$document['courier'], $document['tracking_number']]);
    }

    public function test_waybill_is_100_by_150(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/waybill'))->assertOk();

        $pdf = $this->assertPdf($response, $order->order_number);
        $this->assertStringContainsString('waybill-'.$order->order_number.'.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertMatchesRegularExpression('#/MediaBox \[0 0 283\.\d+ 425\.\d+\]#', $pdf, '100 x 150 mm');
    }

    public function test_bulk_waybills_print_a_page_an_order(): void
    {
        $orders = [$this->order(), $this->order(), $this->order()];
        $query = implode('&', array_map(fn (Order $order) => 'ids[]='.$order->id, $orders));

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/waybills?'.$query))->assertOk();

        $pdf = $this->assertPdf($response, $orders[0]->order_number);
        $this->assertSame(3, preg_match_all('#/Type /Page\b(?!s)#', $pdf));
    }

    public function test_bulk_waybills_need_1_to_100_ids(): void
    {
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/waybills'))->assertRedirect();
        $this->actingAs($this->staff)->getJson($this->siteUrl('/ar/admin/orders/waybills'))->assertStatus(422);
        $this->actingAs($this->staff)->getJson($this->siteUrl('/ar/admin/orders/waybills?'.implode('&', array_map(fn ($id) => 'ids[]='.$id, range(1, 101)))))->assertStatus(422);
        $this->actingAs($this->staff)->getJson($this->siteUrl('/ar/admin/orders/waybills?ids[]=999999'))->assertNotFound();
    }

    public function test_a_waybill_is_refused_for_an_order_that_is_not_shipped(): void
    {
        $pickup = $this->order(overrides: ['delivery_type' => 2]);
        $cancelled = $this->order();
        app(OrderService::class)->changeStatus($cancelled, OrderStatus::CANCELLED, $this->staff->id);

        foreach ([$pickup, $cancelled] as $order) {
            $from = $this->siteUrl('/ar/admin/orders/'.$order->id.'/edit');

            $this->actingAs($this->staff)->from($from)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/waybill'))
                ->assertRedirect($from)
                ->assertSessionHas('error', fn (string $message) => str_contains($message, $order->order_number));
        }

        // The invoice is still there for any order.
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/'.$cancelled->id.'/invoice'))->assertOk();
    }

    public function test_bulk_waybills_skip_orders_that_are_not_shipped(): void
    {
        $first = $this->order();
        $second = $this->order();
        $pickup = $this->order(overrides: ['delivery_type' => 2]);
        $cancelled = $this->order();
        app(OrderService::class)->changeStatus($cancelled, OrderStatus::CANCELLED, $this->staff->id);
        $query = implode('&', array_map(fn (Order $order) => 'ids[]='.$order->id, [$first, $pickup, $second, $cancelled]));

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/waybills?'.$query))->assertOk()
            ->assertSessionHas('error', fn (string $message) => str_contains($message, $pickup->order_number) && str_contains($message, $cancelled->order_number)
                && ! str_contains($message, $first->order_number) && str_contains($message, OrderStatus::CANCELLED->getLabel()));

        $this->assertSame(2, preg_match_all('#/Type /Page\b(?!s)#', $this->assertPdf($response, $first->order_number)));

        // None left to print: back to the list with the reason.
        $this->actingAs($this->staff)->from($this->siteUrl('/ar/admin/orders'))->get($this->siteUrl('/ar/admin/orders/waybills?ids[]='.$pickup->id))
            ->assertRedirect($this->siteUrl('/ar/admin/orders'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, $pickup->order_number));
    }

    public function test_bulk_waybills_with_deleted_and_unknown_ids(): void
    {
        $kept = $this->order();
        $deleted = $this->order();
        app(OrderService::class)->delete($deleted, $this->staff->id);

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/orders/waybills?ids[]='.$kept->id.'&ids[]='.$deleted->id.'&ids[]=999999'))->assertOk();

        $this->assertSame(1, preg_match_all('#/Type /Page\b(?!s)#', $this->assertPdf($response, $kept->order_number)));
    }

    public function test_what_the_buyer_typed_is_escaped_in_the_pdfs(): void
    {
        $order = $this->order(overrides: ['customer_name' => '<b>x</b>', 'delivery_address' => '<img src=x>', 'notes' => '<script>alert(1)</script>']);
        $document = OrderDocument::for($order, 'ar');

        foreach ([view('items::pdf.invoice', ['doc' => $document])->render(), view('items::pdf.waybill', ['docs' => [$document]])->render()] as $html) {
            $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
            $this->assertStringContainsString('&lt;img src=x&gt;', $html);
            $this->assertStringNotContainsString('<b>x</b>', $html);
            $this->assertStringNotContainsString('<img src=x>', $html);
            $this->assertStringNotContainsString('<script>', $html);
        }
    }

    public function test_documents_need_the_invoice_permission(): void
    {
        $order = $this->order();
        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Order View List', 'Order Update');

        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/invoice'))->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/waybill'))->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/orders/waybills?ids[]='.$order->id))->assertForbidden();

        $viewer->givePermissionTo('Order Invoice');
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/orders/'.$order->id.'/invoice'))->assertOk();
    }

    public function test_super_admin_has_the_invoice_permission(): void
    {
        $this->assertTrue(Role::findByName('Super Admin')->hasPermissionTo('Order Invoice'));
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     */
    private function order(?array $items = null, array $overrides = []): Order
    {
        $items ??= [['product_id' => $this->stocked(10)->id, 'quantity' => 1]];
        $this->storeOrder($items, $overrides)->assertSessionHasNoErrors();

        return $this->lastOrder();
    }

    /**
     * A PDF whose title (the document info, UTF-16) holds the order number.
     */
    private function assertPdf(TestResponse $response, string $number): string
    {
        $response->assertHeader('Content-Type', 'application/pdf');
        $pdf = (string) $response->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString($this->utf16($number), $pdf);

        return $pdf;
    }

    /**
     * The text as mPDF writes it in the document info: UTF-16BE, escaped as a PDF string.
     */
    private function utf16(string $text): string
    {
        return strtr(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'), [')' => '\\)', '(' => '\\(', '\\' => '\\\\', chr(13) => '\r']);
    }
}
