<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\RichText;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\database\factories\FactoryUser;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Support\WhatsAppOrder;

/**
 * The buyer's choice travels in the query string (a partial reload of `quote`) and every price and
 * total comes back from the server.
 */
class ProductPageTest extends StorefrontTestCase
{
    private const PAGE = 'Store/Storefront/ProductPage';

    private Product $shirt;

    private AttributeValue $red;

    private AttributeValue $blue;

    private Product $redShirt;

    private Addon $wrap;

    private AddonOption $giftBox;

    private AddonOption $paper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shirt = Product::factory()->create(['name' => 'قميص كتان', 'slug' => 'linen-shirt', 'base_price' => 100, 'manage_stock' => 0]);

        $color = Attribute::factory()->create(['name' => 'اللون', 'type' => 3]);
        $this->red = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أحمر', 'color_code' => '#B42335']);
        $this->blue = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أزرق', 'color_code' => '#1E3A5F']);
        $this->redShirt = $this->variation($this->red, 120);
        $this->variation($this->blue, 130);

        $this->wrap = Addon::factory()->create(['name' => 'التغليف', 'type' => 1]);
        $this->giftBox = AddonOption::factory()->create(['addon_id' => $this->wrap->id, 'name' => 'علبة هدية', 'price' => 10]);
        $this->paper = AddonOption::factory()->create(['addon_id' => $this->wrap->id, 'name' => 'ورق', 'price' => 3]);
        $this->shirt->addons()->attach($this->wrap->id, ['is_required' => 1, 'min_selection' => 1, 'max_selection' => 1, 'created_by' => FactoryUser::id()]);
        $pivotId = DB::table(config('items.table_prefix').'addon_product')->where('product_id', $this->shirt->id)->value('id');
        // This product sells the gift box for 15 and offers only it and the paper.
        DB::table(config('items.table_prefix').'addon_product_option')->insert([
            ['addon_product_id' => $pivotId, 'addon_option_id' => $this->giftBox->id, 'price' => 15, 'is_default' => 0],
            ['addon_product_id' => $pivotId, 'addon_option_id' => $this->paper->id, 'price' => null, 'is_default' => 0],
        ]);
    }

    public function test_page_shows_the_product(): void
    {
        $props = $this->page();

        $this->assertSame('قميص كتان', $props['product']['name']);
        $this->assertSame(['أحمر', 'أزرق'], array_column($props['options']['attributes'][0]['values'], 'name'));
        $this->assertTrue($props['options']['attributes'][0]['swatch']);
        $this->assertSame('#B42335', $props['options']['attributes'][0]['values'][0]['color']);
        $this->assertSame('علبة هدية', $props['options']['addons'][0]['options'][0]['name']);
        $this->assertSame('15.00 ₪', $props['options']['addons'][0]['options'][0]['price_label']);
    }

    public function test_each_variation_brings_its_own_price_image_and_stock(): void
    {
        $this->redShirt->update(['base_price' => 120, 'sale_price' => 90, 'manage_stock' => 1, 'stock_quantity' => 0]);

        $variants = collect($this->page()['options']['variants'])->keyBy('id');
        $red = $variants[$this->redShirt->id];

        $this->assertSame([(string) $this->red->attribute_id => $this->red->id], $red['values']);
        $this->assertSame('90.00 ₪', $red['price']['label']);
        $this->assertSame('120.00 ₪', $red['price']['was']);
        $this->assertSame(25, $red['price']['percent_off']);
        $this->assertFalse($red['in_stock']);
        $this->assertArrayHasKey('image', $red);
        $this->assertArrayHasKey('max_quantity', $red);
    }

    public function test_the_page_starts_on_the_first_variation_in_stock(): void
    {
        $this->redShirt->update(['manage_stock' => 1, 'stock_quantity' => 0]);

        $quote = $this->page()['quote'];

        $this->assertSame($this->blue->id, $quote['selected'][$this->blue->attribute_id]);
        $this->assertSame('130.00 ₪', $quote['price']['label']);
    }

    public function test_choosing_a_value_selects_the_variation_and_its_price(): void
    {
        $quote = $this->quote($this->red, $this->giftBox->id, 2);
        $this->assertSame($this->redShirt->id, $quote['variant_id']);
        $this->assertSame('270.00 ₪', $quote['total_label']); // (120 + 15) × 2

        $this->assertSame('290.00 ₪', $this->quote($this->blue, $this->giftBox->id, 2)['total_label']); // (130 + 15) × 2
    }

    public function test_an_incomplete_choice_shows_the_lowest_price_as_from(): void
    {
        $quote = $this->choose(['addons' => [$this->wrap->id => $this->paper->id], 'quantity' => 1]);

        $this->assertNull($quote['variant_id']);
        $this->assertSame('120.00 ₪', $quote['price_from']);
        $this->assertContains('اختر اللون', $quote['problems']);
    }

    public function test_a_missing_required_addon_blocks_ordering(): void
    {
        $this->whatsapp('970599000000');

        $quote = $this->quote($this->red);
        $this->assertNull($quote['whatsapp_url']);
        $this->assertContains('اختر التغليف', $quote['problems']);

        $this->assertNotNull($this->quote($this->red, $this->paper->id)['whatsapp_url']);
    }

    public function test_an_option_the_product_does_not_offer_is_ignored(): void
    {
        $this->whatsapp('970599000000');
        $other = AddonOption::factory()->create(['addon_id' => $this->wrap->id, 'price' => 50]);

        $quote = $this->quote($this->red, $other->id);

        $this->assertSame(120.0, (float) $quote['total']);
        $this->assertNull($quote['whatsapp_url']);
    }

    public function test_quantity_is_capped_by_the_order_limit_and_stock(): void
    {
        $this->redShirt->update(['max_quantity_per_order' => 5]);
        $this->assertSame(5, $this->quote($this->red, null, 9)['quantity']);

        $this->redShirt->update(['max_quantity_per_order' => null, 'manage_stock' => 1, 'stock_quantity' => 3]);
        $this->assertSame(3, $this->quote($this->red, null, 9)['quantity']);
        $this->assertSame(1, $this->quote($this->red, null, 0)['quantity']);
    }

    public function test_out_of_stock_variation_cannot_be_ordered(): void
    {
        $this->whatsapp('970599000000');
        $this->redShirt->update(['manage_stock' => 1, 'stock_quantity' => 0]);

        $quote = $this->quote($this->red, $this->paper->id);

        $this->assertFalse($quote['in_stock']);
        $this->assertContains('غير متوفر حالياً', $quote['problems']);
        $this->assertNull($quote['whatsapp_url']);
    }

    public function test_whatsapp_link_has_the_choice_and_total(): void
    {
        $this->whatsapp('+970 599 000 000');
        $this->siteName('Maison');

        $url = $this->quote($this->red, $this->giftBox->id, 2)['whatsapp_url'];
        $this->assertStringStartsWith('https://wa.me/970599000000?text=', $url);

        $text = rawurldecode(substr($url, strlen('https://wa.me/970599000000?text=')));
        foreach (['قميص كتان', 'أحمر', 'علبة هدية', '2', '270.00 ₪', '/ar/product/linen-shirt'] as $part) {
            $this->assertStringContainsString($part, $text);
        }
    }

    public function test_no_whatsapp_button_without_a_number(): void
    {
        $props = $this->page('?quantity=1');

        $this->assertFalse($props['hasWhatsapp']);
        $this->assertNull($this->quote($this->red, $this->paper->id)['whatsapp_url']);
    }

    public function test_simple_product_without_options_can_be_ordered(): void
    {
        $this->whatsapp('970599000000');
        Product::factory()->create(['name' => 'كوب', 'slug' => 'mug', 'base_price' => 20, 'sale_price' => 15, 'manage_stock' => 0]);

        $quote = $this->props('/ar/product/mug', self::PAGE)['quote'];

        $this->assertSame('15.00 ₪', $quote['price']['label']);
        $this->assertSame('20.00 ₪', $quote['price']['was']);
        $this->assertNotNull($quote['whatsapp_url']);
    }

    public function test_the_description_passes_through_rich_text(): void
    {
        $translation = $this->shirt->translate('ar');
        $translation->description = '<p>كتان</p><script>alert(1)</script>';
        $translation->save();

        $description = $this->page()['product']['description'];

        $this->assertSame(RichText::make('<p>كتان</p><script>alert(1)</script>')->toHtml(), $description);
        $this->assertStringNotContainsString('<script', $description);
    }

    public function test_unpublished_products_are_the_store_404(): void
    {
        Product::factory()->draft()->create(['slug' => 'draft-one']);

        $this->get($this->siteUrl('/ar/product/draft-one'))->assertNotFound();
        $this->get($this->siteUrl('/ar/product/'.$this->redShirt->slug))->assertNotFound();
        $this->get($this->siteUrl('/ar/product/nothing-here'))->assertNotFound();
    }

    public function test_related_products_are_listed(): void
    {
        $category = Category::factory()->create();
        $this->shirt->categories()->attach($category->id);
        $sibling = Product::factory()->create(['name' => 'قميص قطن']);
        $sibling->categories()->attach($category->id);

        $this->assertContains('قميص قطن', array_column($this->page()['related'], 'name'));
    }

    public function test_props_do_not_carry_cost_or_wholesale_prices(): void
    {
        $json = json_encode($this->page());

        foreach (['cost', 'wholesale', 'stock_quantity'] as $column) {
            $this->assertStringNotContainsString($column, $json);
        }
    }

    public function test_checkbox_addons_work_as_the_browser_sends_them(): void
    {
        $extras = Addon::factory()->create(['name' => 'إضافات', 'type' => 2]);
        $pin = AddonOption::factory()->create(['addon_id' => $extras->id, 'name' => 'دبوس', 'price' => 4]);
        $card = AddonOption::factory()->create(['addon_id' => $extras->id, 'name' => 'بطاقة', 'price' => 6]);
        $this->shirt->addons()->attach($extras->id, ['is_required' => 0, 'min_selection' => 1, 'max_selection' => 2, 'created_by' => FactoryUser::id()]);

        $this->assertSame([], $this->page()['quote']['addons'][$extras->id]); // a list, so the browser collects the ticked values

        $choice = ['selected' => [$this->red->attribute_id => $this->red->id], 'quantity' => 1];
        $total = fn (mixed $extra) => (float) $this->choose([...$choice, 'addons' => [$this->wrap->id => (string) $this->paper->id, $extras->id => $extra]])['total'];

        $this->assertSame(133.0, $total([(string) $pin->id, (string) $card->id])); // 120 + 3 + 4 + 6
        $this->assertSame(123.0, $total('true')); // never valid for a checkbox group
    }

    public function test_a_default_option_is_preselected(): void
    {
        DB::table(config('items.table_prefix').'addon_product_option')->where('addon_option_id', $this->paper->id)->update(['is_default' => 1]);

        $this->assertSame($this->paper->id, $this->page()['quote']['addons'][$this->wrap->id]);
    }

    public function test_an_impossible_minimum_does_not_block_ordering(): void
    {
        $this->whatsapp('970599000000');
        DB::table(config('items.table_prefix').'addon_product')->where('product_id', $this->shirt->id)->update(['min_selection' => 3]);

        $this->assertNotNull($this->quote($this->red, $this->paper->id)['whatsapp_url']); // a radio allows one: min is capped at max
    }

    public function test_an_empty_or_bad_quantity_is_reset(): void
    {
        $this->assertSame(1, $this->choose(['quantity' => ''])['quantity']);
        $this->assertSame(1, $this->choose(['quantity' => 'abc'])['quantity']);
        $this->assertSame(3, $this->choose(['quantity' => '3'])['quantity']);
    }

    public function test_a_malformed_choice_in_the_url_does_not_break_the_page(): void
    {
        $this->get($this->siteUrl('/ar/product/linen-shirt?selected=x&addons=y&quantity[]=z'))->assertOk();
        $this->get($this->siteUrl('/ar/product/linen-shirt?selected[abc]=1&selected[1][]=2&addons[1][2][3]=4'))->assertOk();
    }

    public function test_an_inactive_value_is_not_offered(): void
    {
        $this->blue->update(['is_active' => 0]);

        $this->assertSame(['أحمر'], array_column($this->page()['options']['attributes'][0]['values'], 'name'));
    }

    public function test_whatsapp_link_helper(): void
    {
        $this->assertSame('https://wa.me/970599000000?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%20hi', WhatsAppOrder::link('+970 599-000-000', 'مرحبا hi'));
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $query = ''): array
    {
        return $this->props('/ar/product/linen-shirt'.$query, self::PAGE);
    }

    /**
     * @param  array<string, mixed>  $choice
     * @return array<string, mixed>
     */
    private function choose(array $choice): array
    {
        return $this->page('?'.http_build_query($choice))['quote'];
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(AttributeValue $value, ?int $option = null, int $quantity = 1): array
    {
        return $this->choose([
            'selected' => [$value->attribute_id => $value->id],
            'addons' => $option ? [$this->wrap->id => $option] : [],
            'quantity' => $quantity,
        ]);
    }

    private function variation(AttributeValue $value, float $price): Product
    {
        $variation = Product::factory()->variationOf($this->shirt)->create(['base_price' => $price, 'manage_stock' => 0]);
        $variation->attributeValues()->attach($value->id);

        return $variation;
    }

    private function whatsapp(string $number): void
    {
        app(StoreAppearanceService::class)->save([], ['whatsapp' => $number], []);
    }
}
