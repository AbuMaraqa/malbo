<?php

namespace Tests\Feature\Store;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\database\factories\FactoryUser;

/**
 * The badges of a product card and of the product page: the product's labels, new, best seller,
 * offer, limited quantity and out of stock.
 */
class ProductBadgesTest extends StorefrontTestCase
{
    public function test_cards_carry_label_new_and_best_seller_badges(): void
    {
        $label = Label::factory()->create(['name' => 'حصري', 'slug' => 'exclusive', 'text_color' => '#FFFFFF', 'background_color' => '#1F6F50']);
        $product = $this->product(['is_new' => true, 'is_best_seller' => true]);
        $product->labels()->attach($label->id);

        $badges = $this->cardBadges($product);

        $this->assertSame(['label:exclusive', 'new', 'best_seller'], array_column($badges, 'key'));
        $this->assertSame(['حصري', 'جديد', 'الأكثر مبيعاً'], array_column($badges, 'label'));
        $this->assertSame('#FFFFFF', $badges[0]['text_color']);
        $this->assertSame('#1F6F50', $badges[0]['background_color']);
        $this->assertNotSame('', $badges[1]['tone']);
    }

    public function test_label_colors_cannot_carry_anything_but_a_color(): void
    {
        $label = Label::factory()->create(['slug' => 'odd', 'text_color' => 'red; background: url(x)', 'background_color' => '#1F6F50']);
        $product = $this->product();
        $product->labels()->attach($label->id);

        $badge = $this->cardBadges($product)[0];

        $this->assertNull($badge['text_color']);
        $this->assertSame('#1F6F50', $badge['background_color']);
    }

    public function test_offer_badge_means_the_displayed_price_is_discounted(): void
    {
        $onSale = $this->product(['base_price' => 100, 'sale_price' => 80]);
        $withOfferRecord = $this->product();
        $this->offer($withOfferRecord, ['start_date' => now()->subDay(), 'end_date' => now()->addDay()]);

        $this->assertContains('offer', $this->keys($onSale));
        $this->assertNotContains('offer', $this->keys($withOfferRecord), 'an Offer record does not change the storefront price, so no badge');
    }

    public function test_an_offer_badge_follows_the_clock_through_the_cache(): void
    {
        $product = $this->product(['base_price' => 100, 'discount_type' => 'percentage', 'discount_value' => 10, 'discount_start_date' => now()->addMinutes(5)]);
        $withVariants = $this->product();
        $this->variant($withVariants, 3, ['base_price' => 100, 'discount_type' => 'fixed', 'discount_value' => 5, 'discount_start_date' => now()->addMinutes(5)]);

        $this->assertNotContains('offer', $this->keys($product));
        $this->assertNotContains('offer', $this->keys($withVariants));

        $this->travel(6)->minutes();

        $this->assertContains('offer', $this->keys($product), 'the cache holds the price columns, not the decision');
        $this->assertContains('offer', $this->keys($withVariants));
    }

    public function test_a_product_with_variants_is_on_offer_when_an_orderable_variant_is_discounted(): void
    {
        $parentOnly = $this->product(['base_price' => 100, 'sale_price' => 70]);
        $this->variant($parentOnly, 3, ['base_price' => 100]);

        $clearance = $this->product(['base_price' => 100]);
        $this->variant($clearance, 3, ['base_price' => 100]);
        $this->variant($clearance, 2, ['base_price' => 100, 'sale_price' => 60]);

        $soldOut = $this->product(['base_price' => 100]);
        $this->variant($soldOut, 3, ['base_price' => 100]);
        $this->variant($soldOut, 0, ['base_price' => 100, 'sale_price' => 60]);

        $this->assertNotContains('offer', $this->keys($parentOnly), 'every variant is at full price');
        $this->assertContains('offer', $this->keys($clearance));
        $this->assertNotContains('offer', $this->keys($soldOut), 'the discounted variant cannot be ordered');
    }

    public function test_each_variant_on_the_product_page_says_whether_it_is_on_offer(): void
    {
        $product = $this->product(['slug' => 'per-variant', 'base_price' => 100]);
        $color = Attribute::factory()->create(['type' => 3]);
        $full = $this->variant($product, 3, ['base_price' => 100]);
        $sale = $this->variant($product, 3, ['base_price' => 100, 'sale_price' => 80]);
        $full->attributeValues()->attach(AttributeValue::factory()->create(['attribute_id' => $color->id])->id);
        $sale->attributeValues()->attach(AttributeValue::factory()->create(['attribute_id' => $color->id])->id);

        $variants = collect($this->props('/ar/product/per-variant', 'Store/Storefront/ProductPage')['options']['variants'])->keyBy('id');

        $this->assertFalse($variants[$full->id]['on_offer']);
        $this->assertTrue($variants[$sale->id]['on_offer']);
    }

    public function test_a_variant_with_a_hidden_value_does_not_count_for_stock(): void
    {
        $product = $this->product(['low_stock_threshold' => 2]);
        $size = Attribute::factory()->create(['type' => 1]);
        $shown = $this->variant($product, 0);
        $shown->attributeValues()->attach(AttributeValue::factory()->create(['attribute_id' => $size->id])->id);
        $hidden = $this->variant($product, 50);
        $hidden->attributeValues()->attach(AttributeValue::factory()->create(['attribute_id' => $size->id, 'is_active' => false])->id);

        $this->assertContains('out_of_stock', $this->keys($product), 'every size a buyer can see is sold out');
    }

    public function test_limited_quantity_uses_the_sum_of_the_variants(): void
    {
        $few = $this->product(['low_stock_threshold' => 10]);
        $this->variant($few, 3);
        $this->variant($few, 4);

        $enough = $this->product(['low_stock_threshold' => 10]);
        $this->variant($enough, 3);
        $this->variant($enough, 9);

        $single = $this->product(['manage_stock' => 1, 'stock_quantity' => 5, 'low_stock_threshold' => 5]);

        $this->assertContains('limited', $this->keys($few));
        $this->assertNotContains('limited', $this->keys($enough));
        $this->assertContains('limited', $this->keys($single));
        $this->assertSame('كمية محدودة', collect($this->cardBadges($few))->firstWhere('key', 'limited')['label']);
    }

    public function test_out_of_stock_badges(): void
    {
        $single = $this->product(['manage_stock' => 1, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $variants = $this->product();
        $this->variant($variants, 0);
        $this->variant($variants, 0);
        $marked = $this->product(['manage_stock' => 0, 'stock_status' => 'out_of_stock']);

        foreach ([$single, $variants, $marked] as $product) {
            $keys = $this->keys($product);
            $this->assertContains('out_of_stock', $keys);
            $this->assertNotContains('limited', $keys);
        }
    }

    public function test_unmanaged_or_backorder_stock_has_no_stock_badge(): void
    {
        $unmanaged = $this->product(['manage_stock' => 0]);
        $backorder = $this->product(['manage_stock' => 1, 'stock_quantity' => 0, 'allow_backorder' => true, 'stock_status' => 'on_backorder']);
        $mixed = $this->product();
        $this->variant($mixed, 1);
        $this->variant($mixed, 0, ['manage_stock' => 0, 'stock_status' => 'in_stock']);
        $inactiveOnly = $this->product();
        $this->variant($inactiveOnly, 2);
        $this->variant($inactiveOnly, 50, ['is_active' => 0]);

        foreach ([$unmanaged, $backorder, $mixed] as $product) {
            $this->assertSame([], array_values(array_intersect(['limited', 'out_of_stock'], $this->keys($product))));
        }
        $this->assertContains('limited', $this->keys($inactiveOnly), 'a hidden variation is not counted');
    }

    public function test_the_product_page_has_the_same_badges(): void
    {
        $product = $this->product(['slug' => 'badged', 'is_new' => true, 'base_price' => 100]);
        $this->variant($product, 2, ['base_price' => 100, 'sale_price' => 70]);

        $page = $this->props('/ar/product/badged', 'Store/Storefront/ProductPage');

        $this->assertSame(['new', 'offer', 'limited'], array_column($page['badges'], 'key'));
        $this->assertSame($this->cardBadges($product), $page['badges']);
    }

    public function test_cards_load_no_relation_lazily(): void
    {
        $label = Label::factory()->create();
        foreach (range(1, 3) as $i) {
            $product = $this->product();
            $product->labels()->attach($label->id);
            $this->variant($product, $i);
            $this->offer($product);
        }

        $lazy = [];
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$lazy): void {
            $lazy[] = $model::class.'::'.$relation;
        });

        $this->props('/ar/shop?q=%D9%85', 'Store/Storefront/Shop');
        $this->props('/ar/shop', 'Store/Storefront/Shop');
        $this->props('/ar', 'Store/Storefront/Home');

        $this->assertSame([], array_values(array_unique($lazy)));
    }

    // ---------------------------------------------------------------- helpers

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['manage_stock' => 0, ...$attributes]);
    }

    private function variant(Product $parent, int $stock, array $attributes = []): Product
    {
        return Product::factory()->variationOf($parent)->create([
            'manage_stock' => 1,
            'stock_quantity' => $stock,
            'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
            ...$attributes,
        ]);
    }

    private function offer(Product $product, array $attributes = []): Offer
    {
        $offer = new Offer([
            'type' => 1,
            'value' => 10,
            'is_active' => true,
            'offerable_type' => $product->getMorphClass(),
            'offerable_id' => $product->id,
            'created_by' => FactoryUser::id(),
            ...$attributes,
        ]);
        Translations::fill($offer, ['ar' => ['name' => 'عرض']]);
        $offer->save();

        return $offer;
    }

    /**
     * The badges of $product's card on the shop page.
     *
     * @return list<array<string, mixed>>
     */
    private function cardBadges(Product $product): array
    {
        $cards = collect($this->props('/ar/shop', 'Store/Storefront/Shop')['products']['data']);

        return $cards->firstWhere('id', $product->id)['badges'];
    }

    /**
     * @return list<string>
     */
    private function keys(Product $product): array
    {
        return array_column($this->cardBadges($product), 'key');
    }
}
