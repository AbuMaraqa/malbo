<?php

namespace Tests\Feature\Store;

use Modules\Core\Support\RichText;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;

/**
 * The product page's "Details" section, its size guide and its video.
 */
class ProductDetailsPageTest extends StorefrontTestCase
{
    private const PAGE = 'Store/Storefront/ProductPage';

    private Product $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shirt = Product::factory()->create([
            'name' => 'قميص كتان',
            'slug' => 'linen-shirt',
            'manage_stock' => 0,
            'fabric' => 'كتان',
            'composition' => '100% كتان',
            'fit' => 'قصة عادية',
            'care' => 'غسيل بارد',
            'model_height' => 185,
            'model_weight' => 78,
            'model_size' => 'L',
        ]);
    }

    public function test_the_page_shows_the_details_section(): void
    {
        $details = collect($this->page()['product']['details'])->keyBy('key');

        $this->assertSame(['fabric', 'composition', 'fit', 'care', 'model'], $details->keys()->all());
        $this->assertSame('القماش', $details['fabric']['label']);
        $this->assertSame('كتان', $details['fabric']['value']);
        $this->assertSame('100% كتان', $details['composition']['value']);
        $this->assertStringContainsString('185', $details['model']['value']);
        $this->assertStringContainsString('78', $details['model']['value']);
        $this->assertStringContainsString('L', $details['model']['value']);
    }

    public function test_a_variation_overrides_only_what_it_fills(): void
    {
        $color = Attribute::factory()->create(['name' => 'اللون', 'type' => 3]);
        $red = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أحمر', 'color_code' => '#B42335']);
        $variation = Product::factory()->variationOf($this->shirt)->create(['manage_stock' => 0, 'fit' => 'قصة ضيقة', 'model_size' => 'M']);
        $variation->attributeValues()->attach($red->id);

        $variant = collect($this->page()['options']['variants'])->firstWhere('id', $variation->id);
        $details = collect($variant['details'])->keyBy('key');

        $this->assertSame('قصة ضيقة', $details['fit']['value']);
        $this->assertSame('كتان', $details['fabric']['value'], 'empty on the variation: the product’s own');
        $this->assertStringContainsString('M', $details['model']['value']);
        $this->assertStringContainsString('185', $details['model']['value']);
    }

    public function test_no_details_means_an_empty_list(): void
    {
        Product::factory()->create(['slug' => 'plain-mug', 'manage_stock' => 0]);

        $product = $this->props('/ar/product/plain-mug', self::PAGE)['product'];

        $this->assertSame([], $product['details']);
        $this->assertNull($product['size_guide']);
        $this->assertNull($product['video']);
    }

    public function test_the_video_is_sent_as_a_privacy_friendly_embed(): void
    {
        $this->shirt->update(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->assertSame(
            ['type' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            $this->page()['product']['video'],
        );
    }

    public function test_the_size_guide_comes_from_the_first_category_that_has_one(): void
    {
        $plain = Category::factory()->create(['name' => 'قمصان']);
        $guided = Category::factory()->create(['name' => 'رجالي', 'size_guide' => '<table><tr><td>L</td><td>102</td></tr></table><script>alert(1)</script>']);
        $later = Category::factory()->create(['name' => 'صيفي', 'size_guide' => '<p>غيره</p>']);
        $this->shirt->categories()->attach([$plain->id, $guided->id, $later->id]);

        $guide = $this->page()['product']['size_guide'];

        $this->assertSame('رجالي', $guide['title']);
        $this->assertSame(RichText::make('<table><tr><td>L</td><td>102</td></tr></table><script>alert(1)</script>')->toHtml(), $guide['html']);
        $this->assertStringNotContainsString('<script', $guide['html']);
        $this->assertStringContainsString('102', $guide['html']);
    }

    public function test_the_size_guide_walks_up_to_the_nearest_parent_that_has_one(): void
    {
        $top = Category::factory()->create(['name' => 'ملابس', 'size_guide' => '<p>عام</p>']);
        $men = Category::factory()->create(['name' => 'رجالي', 'parent_id' => $top->id, 'size_guide' => '<p>رجالي</p>']);
        $shirts = Category::factory()->create(['name' => 'قمصان', 'parent_id' => $men->id]);
        $this->shirt->categories()->attach($shirts->id);

        $this->assertSame(['title' => 'رجالي', 'html' => '<p>رجالي</p>'], $this->page()['product']['size_guide']);

        $men->translate('ar')->size_guide = null;
        $men->save();

        $this->assertSame('ملابس', $this->page()['product']['size_guide']['title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return $this->props('/ar/product/linen-shirt', self::PAGE);
    }
}
