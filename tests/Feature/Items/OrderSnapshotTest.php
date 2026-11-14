<?php

namespace Tests\Feature\Items;

use Modules\Items\Entities\OrderItem;
use Modules\Items\Enums\OrderChannel;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * An order line keeps what was sold (SKU, name, color and size) whatever happens to the product
 * later. Internal notes are for staff only.
 */
class OrderSnapshotTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrders();
    }

    public function test_a_new_line_keeps_sku_name_and_options(): void
    {
        [, $variant] = $this->shirtWithVariant();

        $this->storeOrder([['product_id' => $variant->id, 'quantity' => 1]])->assertSessionHasNoErrors();

        /** @var OrderItem $item */
        $item = $this->lastOrder()->items()->firstOrFail();

        $this->assertSame('SHIRT-BLK-L', $item->sku);
        $this->assertSame('قميص', $item->product_name['ar']);
        $this->assertSame('Shirt', $item->product_name['en']);
        $this->assertCount(2, $item->options);
        $this->assertEqualsCanonicalizing(['Black', 'L'], array_map(fn (array $option) => $option['value']['en'], $item->options));
        $this->assertEqualsCanonicalizing(['Color', 'Size'], array_map(fn (array $option) => $option['name']['en'], $item->options));
    }

    public function test_the_snapshot_survives_a_rename(): void
    {
        [$shirt, $variant] = $this->shirtWithVariant();
        $this->storeOrder([['product_id' => $variant->id, 'quantity' => 1]])->assertSessionHasNoErrors();

        $shirt->translateOrNew('ar')->name = 'اسم جديد';
        $shirt->save();

        $this->assertSame('قميص', $this->lastOrder()->items()->firstOrFail()->product_name['ar']);
    }

    public function test_an_edited_line_keeps_its_snapshot_while_its_product_stays(): void
    {
        [$shirt, $variant] = $this->shirtWithVariant();
        $this->storeOrder([['product_id' => $variant->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $line = $order->items()->firstOrFail();

        $shirt->translateOrNew('ar')->name = 'اسم جديد';
        $shirt->save();
        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $variant->id, 'quantity' => 2]])->assertSessionHasNoErrors();

        $this->assertSame('قميص', $line->fresh()->product_name['ar']);
    }

    public function test_internal_notes_and_customer_are_saved_but_not_sent_to_the_customer_api(): void
    {
        $product = $this->stocked(5);

        $this->storeOrder([['product_id' => $product->id, 'quantity' => 1]], [
            'internal_notes' => 'اتصل قبل التوصيل',
            'customer_name' => 'أحمد',
            'customer_email' => 'ahmad@example.com',
        ])->assertSessionHasNoErrors();

        $order = $this->lastOrder();
        $this->assertSame('اتصل قبل التوصيل', $order->internal_notes);
        $this->assertSame('أحمد', $order->customer_name);
        $this->assertSame('ahmad@example.com', $order->customer_email);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson($this->siteUrl('/api/v1/items/orders/'.$order->id))
            ->assertOk()
            ->assertJsonMissingPath('data.internal_notes');
    }

    public function test_the_edit_page_sends_the_internal_notes(): void
    {
        $product = $this->stocked(5);
        $this->storeOrder([['product_id' => $product->id, 'quantity' => 1]], ['internal_notes' => 'ملاحظة'])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)
            ->get($this->siteUrl('/ar/admin/orders/'.$this->lastOrder()->id.'/edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.internal_notes', 'ملاحظة')->where('order.channel', 'b2c'));
    }

    public function test_channel_defaults_to_b2c(): void
    {
        $product = $this->stocked(5);

        $this->storeOrder([['product_id' => $product->id, 'quantity' => 1]])->assertSessionHasNoErrors();

        $this->assertSame(OrderChannel::B2C, $this->lastOrder()->channel);
    }
}
