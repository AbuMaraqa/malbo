<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Tag;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Tests\DatabaseTestCase;

/**
 * The shop's search: names in any language, SKUs, tags and categories, best matches first.
 */
class StorefrontSearchTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
    }

    public function test_search_matches_a_name_in_any_locale(): void
    {
        $shirt = Product::factory()->create(['name' => 'قميص كتان']);
        $shirt->translateOrNew('en')->name = 'Linen shirt';
        $shirt->translateOrNew('he')->name = 'חולצת פשתן';
        $shirt->save();
        Product::factory()->create(['name' => 'بنطال']);

        $this->assertSame([$shirt->id], $this->search('كتان'));
        $this->assertSame([$shirt->id], $this->search('LINEN'));
        $this->assertSame([$shirt->id], $this->search('פשתן'));
    }

    public function test_search_matches_the_parent_or_a_variant_sku(): void
    {
        $parent = Product::factory()->create(['sku' => 'MLB-SHIRT-01']);
        Product::factory()->variationOf($parent)->create(['sku' => 'MLB-SHIRT-01-BLK-XL']);
        $deletedVariantOf = Product::factory()->create(['sku' => 'OTHER-1']);
        Product::factory()->variationOf($deletedVariantOf)->create(['sku' => 'GONE-XL'])->delete();
        $inactiveVariantOf = Product::factory()->create(['sku' => 'OTHER-2']);
        Product::factory()->variationOf($inactiveVariantOf)->create(['sku' => 'HIDDEN-XL', 'is_active' => 0]);
        Product::factory()->create(['sku' => 'MLB-PANTS-02']);

        $this->assertSame([$parent->id], $this->search('mlb-shirt'));
        $this->assertSame([$parent->id], $this->search('blk-xl'));
        $this->assertSame([], $this->search('GONE-XL'));
        $this->assertSame([], $this->search('HIDDEN-XL'));
    }

    public function test_an_exact_sku_comes_first(): void
    {
        $named = Product::factory()->create(['name' => 'MLB-7 قميص', 'sku' => 'AAA-1']);
        $bySku = Product::factory()->create(['name' => 'بنطال', 'sku' => 'ZZZ-1']);
        Product::factory()->variationOf($bySku)->create(['sku' => 'mlb-7']);
        $this->age($bySku, 5);

        $this->assertSame([$bySku->id, $named->id], $this->search('MLB-7', null));
    }

    public function test_arabic_letter_forms_and_marks_match(): void
    {
        $red = Product::factory()->create(['name' => 'قميص أحمر']);
        $dress = Product::factory()->create(['name' => 'فستان سهرة']);
        $marked = Product::factory()->create(['name' => 'بنطال كلاسيكي']);
        Product::factory()->create(['name' => 'حذاء']);

        $this->assertSame([$red->id], $this->search('احمر'));
        $this->assertSame([$dress->id], $this->search('فستان سهره'));
        $this->assertSame([$marked->id], $this->search('بَنْطَال كلاسيـكي'));
    }

    public function test_every_word_must_match_a_name_sku_tag_category_or_a_visible_variant_value(): void
    {
        $color = Attribute::factory()->create(['name' => 'اللون', 'type' => AttributeType::COLOR->value]);
        $black = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أسود', 'is_active' => true]);
        $white = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أبيض', 'is_active' => true]);
        $hidden = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'مخفي', 'is_active' => false]);

        $blackShirt = Product::factory()->create(['name' => 'قميص كتان']);
        Product::factory()->variationOf($blackShirt)->create()->attributeValues()->attach($black->id);
        $whiteShirt = Product::factory()->create(['name' => 'قميص قطن']);
        Product::factory()->variationOf($whiteShirt)->create()->attributeValues()->attach($white->id);
        $hiddenBlack = Product::factory()->create(['name' => 'قميص صوف']);
        Product::factory()->variationOf($hiddenBlack)->create()->attributeValues()->attach([$black->id, $hidden->id]);
        $inactiveBlack = Product::factory()->create(['name' => 'قميص حرير']);
        Product::factory()->variationOf($inactiveBlack)->create(['is_active' => 0])->attributeValues()->attach($black->id);
        $blackPants = Product::factory()->create(['name' => 'بنطال']);
        Product::factory()->variationOf($blackPants)->create()->attributeValues()->attach($black->id);

        $this->assertSame([$blackShirt->id], $this->search('قميص اسود'));
        $this->assertSame([$whiteShirt->id], $this->search('  قميص   قطن '));
    }

    public function test_like_wildcards_are_literal(): void
    {
        $underscore = Product::factory()->create(['name' => 'a_b']);
        $percent = Product::factory()->create(['name' => 'خصم 100%']);
        Product::factory()->create(['name' => 'axb']);

        $this->assertSame([$underscore->id], $this->search('a_b'));
        $this->assertSame([$underscore->id], $this->search('_'));
        $this->assertSame([$percent->id], $this->search('%'));
    }

    public function test_search_matches_tag_and_category_names(): void
    {
        $tagged = Product::factory()->create(['name' => 'أ']);
        $tag = Tag::query()->create(['slug' => 'summer', 'name' => 'صيفي']);
        $tagged->tags()->attach($tag->id);

        $inCategory = Product::factory()->create(['name' => 'ب']);
        $category = Category::factory()->create(['name' => 'بدلات رسمية']);
        $inCategory->categories()->attach($category->id);

        $inHidden = Product::factory()->create(['name' => 'ج']);
        $hidden = Category::factory()->create(['name' => 'بدلات مخفية', 'status' => CategoryStatus::INACTIVE]);
        $inHidden->categories()->attach($hidden->id);

        $this->assertSame([$tagged->id], $this->search('صيف'));
        $this->assertSame([$inCategory->id], $this->search('بدلات'));
    }

    public function test_exact_then_prefix_name_matches_come_first(): void
    {
        $inside = Product::factory()->create(['name' => 'جاكيت قميص', 'base_price' => 300]);
        $prefix = Product::factory()->create(['name' => 'قميص أبيض', 'base_price' => 200]);
        $exact = Product::factory()->create(['name' => 'قميص', 'base_price' => 100]);
        $this->age($exact, 3); // the oldest: the rank wins over the date
        $this->age($prefix, 2);

        // Most relevant: what a search sorts by unless the shopper picks another order.
        $this->assertSame([$exact->id, $prefix->id, $inside->id], $this->search('قميص', null));
        $this->assertSame([$exact->id, $prefix->id, $inside->id], $this->search('قميص', 'relevance'));

        // A sort the shopper picks is the order, whatever the rank.
        $this->assertSame([$inside->id, $prefix->id, $exact->id], $this->search('قميص', 'price_desc'));
        $this->assertSame([$inside->id, $prefix->id, $exact->id], $this->search('قميص', 'newest'));
    }

    public function test_the_shop_sorts_a_search_by_relevance_by_default(): void
    {
        URL::defaults(['locale' => 'ar']);
        $sort = function (string $query): string {
            $sort = '';
            $this->get($this->siteUrl('/ar/shop'.$query))->assertOk()->assertInertia(function ($page) use (&$sort) {
                $sort = $page->toArray()['props']['filters']['sort'];
                $page->etc();
            });

            return $sort;
        };

        $this->assertSame('relevance', $sort('?q=abc'));
        $this->assertSame('price_asc', $sort('?q=abc&sort=price_asc'));
        $this->assertSame('newest', $sort(''));
        $this->assertSame('newest', $sort('?sort=relevance')); // nothing to be relevant to
    }

    /**
     * @return list<int>
     */
    private function search(string $q, ?string $sort = 'newest'): array
    {
        return collect(app(ProductRepositoryInterface::class)->storefrontListing(array_filter(['q' => $q, 'sort' => $sort]))->items())->pluck('id')->all();
    }

    private function age(Product $product, int $days): void
    {
        DB::table($product->getTable())->where('id', $product->id)->update(['created_at' => now()->subDays($days)]);
    }
}
