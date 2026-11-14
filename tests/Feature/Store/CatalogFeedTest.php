<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\ProductStatus;
use Tests\Feature\Store\Concerns\MakesMarketing;

/**
 * The product catalog for Facebook and Instagram: one item per variant a buyer can choose (or per
 * product without variants), with storefront prices only.
 */
class CatalogFeedTest extends StorefrontTestCase
{
    use MakesMarketing;

    private const G = 'http://base.google.com/ns/1.0';

    public function test_one_item_per_sellable_variant_or_single_product(): void
    {
        $size = $this->sizeAttribute();
        [$s, $m, $l] = [$this->value($size, 'S'), $this->value($size, 'M', 1), $this->value($size, 'L', 2)];
        $hidden = $this->value($size, 'XL', 3);
        $hidden->update(['is_active' => false]);

        $shirt = Product::factory()->create(['sku' => 'SHIRT']);
        $this->variantOf($shirt, [$s], ['sku' => 'SHIRT-S']);
        $this->variantOf($shirt, [$m], ['sku' => 'SHIRT-M']);
        $this->variantOf($shirt, [$l], ['sku' => 'SHIRT-L', 'is_active' => 0]);
        $this->variantOf($shirt, [$hidden], ['sku' => 'SHIRT-XL']);
        Product::factory()->create(['sku' => 'BELT']);
        // Saving fills a SKU in; a row without one (an import) still gets a stable id.
        $noSku = Product::factory()->create();
        DB::table('items_products')->where('id', $noSku->id)->update(['sku' => '']);

        $items = $this->items();

        $this->assertEqualsCanonicalizing(['SHIRT-S', 'SHIRT-M', 'BELT', 'P'.$noSku->id], array_column($items, 'id'));
        $this->assertSame('SHIRT', $this->item($items, 'SHIRT-S')['item_group_id']);
        $this->assertArrayNotHasKey('item_group_id', $this->item($items, 'BELT'));
    }

    public function test_item_fields(): void
    {
        Storage::fake('items');
        $size = $this->sizeAttribute();
        $color = Attribute::factory()->create(['slug' => 'color', 'name' => 'اللون', 'type' => AttributeType::COLOR->value, 'is_filterable' => true]);
        $red = $this->value($color, 'أحمر');
        $blue = $this->value($color, 'أزرق', 1);
        $brand = Brand::factory()->create(['name' => 'MALBO Studio']);

        $shirt = Product::factory()->create([
            'sku' => 'SHIRT', 'slug' => 'oxford-shirt', 'name' => 'قميص أكسفورد', 'base_price' => 100, 'brand_id' => $brand->id,
            'description' => '<p>قطن <strong>مصري</strong> &amp; ناعم</p><script>alert(1)</script>',
        ]);
        $redM = $this->variantOf($shirt, [$this->value($size, 'M'), $red], ['sku' => 'SHIRT-RED-M', 'base_price' => 100, 'stock_quantity' => 3]);
        $blueM = $this->variantOf($shirt, [$this->value($size, 'L', 1), $blue], ['sku' => 'SHIRT-BLUE-L', 'base_price' => 100, 'stock_quantity' => 0]);

        $shirt->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('product', 'items');
        $shirt->addMedia(UploadedFile::fake()->image('red.jpg'))->withCustomProperties(['attribute_value_id' => $red->id])->toMediaCollection('product_gallery', 'items');
        $shirt->touch();

        $items = $this->items();
        $item = $this->item($items, 'SHIRT-RED-M');

        $this->assertSame('قميص أكسفورد', $item['title']);
        $this->assertSame('قطن مصري & ناعم', $item['description']);
        $this->assertSame(route('store.product', ['locale' => 'ar', 'slug' => 'oxford-shirt']).'?'.http_build_query(['selected' => [$size->id => $redM->attributeValues->firstWhere('attribute_id', $size->id)->id, $color->id => $red->id]]), $item['link']);
        $this->assertStringStartsWith('http', $item['image_link']);
        $this->assertStringEndsWith('red.jpg', $item['image_link']);
        $this->assertStringEndsWith('main.jpg', $this->item($items, 'SHIRT-BLUE-L')['image_link']);
        $this->assertSame('in stock', $item['availability']);
        $this->assertSame('out of stock', $this->item($items, 'SHIRT-BLUE-L')['availability']);
        $this->assertSame('new', $item['condition']);
        $this->assertSame('100.00 ILS', $item['price']);
        $this->assertArrayNotHasKey('sale_price', $item);
        $this->assertSame('MALBO Studio', $item['brand']);
        $this->assertSame('أحمر', $item['color']);
        $this->assertSame('M', $item['size']);
        $this->assertSame('SHIRT', $item['item_group_id']);
        $this->assertNotNull($blueM);
    }

    public function test_sale_price_and_its_dates(): void
    {
        Carbon::setTestNow('2026-11-15 12:00:00');
        Product::factory()->create(['sku' => 'SALE', 'base_price' => 100, 'sale_price' => 80]);
        Product::factory()->create([
            'sku' => 'WINDOW', 'base_price' => 200, 'discount_type' => 'percentage', 'discount_value' => 10,
            'discount_start_date' => '2026-11-01 00:00:00', 'discount_end_date' => '2026-11-30 00:00:00',
        ]);
        Product::factory()->create(['sku' => 'LATER', 'base_price' => 50, 'discount_type' => 'fixed', 'discount_value' => 5, 'discount_start_date' => '2026-12-01 00:00:00']);

        $items = $this->items();

        $this->assertSame(['100.00 ILS', '80.00 ILS'], [$this->item($items, 'SALE')['price'], $this->item($items, 'SALE')['sale_price']]);
        $this->assertArrayNotHasKey('sale_price_effective_date', $this->item($items, 'SALE'));
        $this->assertSame(['200.00 ILS', '180.00 ILS'], [$this->item($items, 'WINDOW')['price'], $this->item($items, 'WINDOW')['sale_price']]);
        $this->assertSame('2026-11-01T00:00+0000/2026-11-30T23:59+0000', $this->item($items, 'WINDOW')['sale_price_effective_date']);
        $this->assertArrayNotHasKey('sale_price', $this->item($items, 'LATER'));
    }

    public function test_hidden_unpublished_and_inactive_are_left_out(): void
    {
        Product::factory()->create(['sku' => 'LIVE']);
        Product::factory()->create(['sku' => 'DRAFT', 'status' => ProductStatus::DRAFT]);
        Product::factory()->create(['sku' => 'OFF', 'is_active' => 0]);
        Product::factory()->create(['sku' => 'GONE'])->delete();

        $this->assertSame(['LIVE'], array_column($this->items(), 'id'));
    }

    public function test_the_csv_variant(): void
    {
        Product::factory()->create(['sku' => 'BELT', 'name' => 'حزام, جلد', 'base_price' => 45.5, 'manage_stock' => false]);

        $response = $this->get($this->siteUrl('/feeds/meta-catalog.csv'))->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));

        $rows = array_map('str_getcsv', array_filter(explode("\n", $response->getContent())));
        $header = array_shift($rows);
        $this->assertSame(['id', 'item_group_id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'sale_price_effective_date', 'link', 'image_link', 'brand', 'color', 'size', 'product_type'], $header);

        $row = array_combine($header, $rows[0]);
        $this->assertSame(['BELT', 'حزام, جلد', '45.50 ILS', 'in stock'], [$row['id'], $row['title'], $row['price'], $row['availability']]);
    }

    public function test_the_token_is_required_when_set(): void
    {
        Product::factory()->create(['sku' => 'LIVE']);
        $this->marketing(['feed_token' => 'abcdefabcdef1234']);

        $this->get($this->siteUrl('/feeds/meta-catalog.xml'))->assertNotFound();
        $this->get($this->siteUrl('/feeds/meta-catalog.xml?token=wrong'))->assertNotFound();
        $this->get($this->siteUrl('/feeds/meta-catalog.csv'))->assertNotFound();
        $this->get($this->siteUrl('/feeds/meta-catalog.xml?token=abcdefabcdef1234'))->assertOk();
    }

    public function test_it_is_cached_for_an_hour_whatever_sells(): void
    {
        $belt = Product::factory()->create(['sku' => 'BELT', 'base_price' => 40, 'manage_stock' => true, 'stock_quantity' => 5]);
        $this->assertSame(['40.00 ILS', 'in stock'], [$this->item($this->items(), 'BELT')['price'], $this->item($this->items(), 'BELT')['availability']]);

        // Sales and price edits do not throw the feed away (Meta reads it hourly anyway).
        app(\Modules\Items\Services\InventoryService::class)->sell([$belt->id => 5]);
        $belt->update(['base_price' => 60]);
        $this->assertSame(['40.00 ILS', 'in stock'], [$this->item($this->items(), 'BELT')['price'], $this->item($this->items(), 'BELT')['availability']]);

        // An hour later it is built again.
        $this->travel(61)->minutes();
        $this->assertSame(['60.00 ILS', 'out of stock'], [$this->item($this->items(), 'BELT')['price'], $this->item($this->items(), 'BELT')['availability']]);
    }

    public function test_publishing_or_removing_a_product_rebuilds_it(): void
    {
        $belt = Product::factory()->create(['sku' => 'BELT']);
        $this->assertSame(['BELT'], array_column($this->items(), 'id'));

        $cap = Product::factory()->create(['sku' => 'CAP']);
        $this->assertEqualsCanonicalizing(['BELT', 'CAP'], array_column($this->items(), 'id'));

        $cap->update(['status' => ProductStatus::DRAFT]);
        $this->assertSame(['BELT'], array_column($this->items(), 'id'));

        $cap->update(['status' => ProductStatus::PUBLISHED]);
        $belt->delete();
        $this->assertSame(['CAP'], array_column($this->items(), 'id'));
    }

    public function test_no_wholesale_or_cost_price(): void
    {
        $shirt = Product::factory()->create(['sku' => 'SHIRT', 'base_price' => 100, 'cost_price' => 37.13, 'wholesale_price' => 41.17]);
        $this->variantOf($shirt, [$this->value($this->sizeAttribute(), 'M')], ['sku' => 'SHIRT-M', 'cost_price' => 37.13, 'wholesale_price' => 41.17]);

        $xml = $this->get($this->siteUrl('/feeds/meta-catalog.xml'))->assertOk()->getContent();
        $csv = $this->get($this->siteUrl('/feeds/meta-catalog.csv'))->assertOk()->getContent();

        foreach (['37.13', '41.17'] as $secret) {
            $this->assertStringNotContainsString($secret, $xml.$csv);
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function items(): array
    {
        $response = $this->get($this->siteUrl('/feeds/meta-catalog.xml'))->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);

        $items = [];
        foreach ($xml->channel->item as $item) {
            $items[] = array_map('strval', (array) $item->children(self::G));
        }

        return $items;
    }

    /**
     * @param  list<array<string, string>>  $items
     * @return array<string, string>
     */
    private function item(array $items, string $id): array
    {
        $item = collect($items)->firstWhere('id', $id);
        $this->assertNotNull($item, "{$id} is not in the feed");

        return $item;
    }
}
