<?php

namespace Tests\Feature\Items;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Product;
use Modules\Items\Support\ProductVideo;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\EditsProducts;

/**
 * Fabric, composition, fit, care and the model's measures (translated where they are text), the
 * new and best seller flags, and the product video, through the admin product form.
 */
class ProductDetailsTest extends DatabaseTestCase
{
    use EditsProducts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpProductForm();
    }

    public function test_details_flags_and_video_are_saved_and_shown_on_edit(): void
    {
        $this->storeProduct([
            'fabric' => 'كتان',
            'composition' => '100% كتان',
            'fit' => 'قصة عادية',
            'care' => 'غسيل بارد',
            'model_height' => 185,
            'model_weight' => 78,
            'model_size' => 'L',
            'is_new' => true,
            'is_best_seller' => '1',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertSessionHasNoErrors();

        $product = $this->latestParentProduct();
        $this->assertSame('كتان', $product->translate('ar')->fabric);
        $this->assertSame('100% كتان', $product->translate('ar')->composition);
        $this->assertSame(185, (int) $product->model_height);
        $this->assertTrue($product->is_new);
        $this->assertTrue($product->is_best_seller);

        $this->adminPage('/products/'.$product->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.fabric', 'كتان')
            ->where('product.fit', 'قصة عادية')
            ->where('product.care', 'غسيل بارد')
            ->where('product.model_height', 185)
            ->where('product.model_weight', 78)
            ->where('product.model_size', 'L')
            ->where('product.is_new', true)
            ->where('product.is_best_seller', true)
            ->where('product.video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_a_variation_keeps_its_own_details(): void
    {
        $this->storeProduct([
            'fabric' => 'قطن',
            'variations' => [
                $this->variationRow(['name' => 'أحمر', 'fit' => 'قصة ضيقة', 'model_size' => 'M']),
                $this->variationRow(['name' => 'أزرق']),
            ],
        ])->assertSessionHasNoErrors();

        $product = $this->latestParentProduct();
        [$red, $blue] = $product->variations()->with('translations')->orderBy('id')->get()->all();

        $this->assertSame('قصة ضيقة', $red->translate('ar')->fit);
        $this->assertSame('M', $red->model_size);
        $this->assertNull($blue->translate('ar')->fit);
        $this->assertNull($blue->translate('ar')->fabric, 'a variation inherits on the storefront, it does not copy');

        $this->adminPage('/products/'.$product->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('product.variations.0.fit', 'قصة ضيقة')
            ->where('product.variations.0.model_size', 'M')
            ->missing('product.variations.0.video_url')
            ->missing('product.variations.0.is_new'));
    }

    public function test_details_are_saved_in_the_edited_locale_only(): void
    {
        $this->storeProduct(['fabric' => 'كتان'])->assertSessionHasNoErrors();
        $product = $this->latestParentProduct();

        $this->updateProduct($product, ['name' => 'Shirt', 'fabric' => 'Linen'], 'en')->assertSessionHasNoErrors();

        $product = Product::query()->with('translations')->findOrFail($product->id);
        $this->assertSame('كتان', $product->translate('ar')->fabric);
        $this->assertSame('Linen', $product->translate('en')->fabric);
    }

    public function test_video_url_must_be_youtube_vimeo_or_mp4(): void
    {
        foreach (['https://youtu.be/dQw4w9WgXcQ', 'https://vimeo.com/76979871', 'https://cdn.example.com/clips/shirt.mp4'] as $url) {
            $this->storeProduct(['video_url' => $url])->assertSessionHasNoErrors();
        }

        foreach (['https://example.com/page', 'javascript:alert(1)', 'http://cdn.example.com/a.mp4', 'not a url'] as $url) {
            $this->storeProduct(['video_url' => $url])->assertSessionHasErrors('video_url');
        }

        $this->assertSame(['type' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'], ProductVideo::embed('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'));
        $this->assertSame(['type' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'], ProductVideo::embed('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame(['type' => 'vimeo', 'src' => 'https://player.vimeo.com/video/76979871?dnt=1'], ProductVideo::embed('https://vimeo.com/76979871'));
        $this->assertSame(['type' => 'file', 'src' => 'https://cdn.example.com/clips/shirt.mp4'], ProductVideo::embed('https://cdn.example.com/clips/shirt.mp4'));
        $this->assertNull(ProductVideo::embed('https://example.com/page'));
        $this->assertNull(ProductVideo::embed(null));
    }

    public function test_model_numbers_are_validated(): void
    {
        $this->storeProduct(['model_height' => 20, 'model_weight' => 'heavy', 'model_size' => str_repeat('X', 40)])
            ->assertSessionHasErrors(['model_height', 'model_weight', 'model_size']);

        $this->storeProduct(['variations' => [$this->variationRow(['model_height' => 400])]])
            ->assertSessionHasErrors('variations.0.model_height');
    }

    public function test_the_table_filters_new_and_best_seller(): void
    {
        $new = Product::factory()->create(['is_new' => true]);
        $best = Product::factory()->create(['is_best_seller' => true]);

        $only = fn (string $query, Product $product) => $this->adminPage('/products?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $product->id));

        $only('filter[is_new]=1', $new);
        $only('filter[is_best_seller]=1', $best);

        $this->adminPage('/products?filter[is_new]=1')->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.is_new', true)
            ->where('table.data.0.is_best_seller', false));
    }
}
