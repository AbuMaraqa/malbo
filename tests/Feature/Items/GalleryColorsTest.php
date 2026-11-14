<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Store\Services\StorefrontCatalogCache;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\EditsProducts;

/**
 * A gallery image can belong to a color: the media custom property `attribute_value_id`, a value of
 * a COLOR attribute. The storefront shows the chosen color's images.
 */
class GalleryColorsTest extends DatabaseTestCase
{
    use EditsProducts;

    private AttributeValue $red;

    private AttributeValue $blue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpProductForm();

        $color = Attribute::factory()->create(['name' => 'اللون', 'type' => 3]);
        $this->red = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أحمر', 'color_code' => '#B42335']);
        $this->blue = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => 'أزرق', 'color_code' => '#1E3A5F']);
    }

    public function test_a_new_gallery_image_can_be_tied_to_a_color(): void
    {
        $this->storeProduct([
            'gallery' => [UploadedFile::fake()->image('red.jpg'), UploadedFile::fake()->image('any.jpg')],
            'gallery_new_colors' => [$this->red->id, null],
        ])->assertSessionHasNoErrors();

        $product = $this->latestParentProduct();
        [$red, $any] = $product->getMedia('product_gallery')->all();

        $this->assertSame($this->red->id, $red->getCustomProperty('attribute_value_id'));
        $this->assertNull($any->getCustomProperty('attribute_value_id'));

        $this->adminPage('/products/'.$product->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('product.gallery_images.0.color', $this->red->id)
            ->where('product.gallery_images.1.color', null)
            ->where('attributes', fn ($attributes) => collect($attributes)->firstWhere('id', $this->red->attribute_id)['swatch'] === true));
    }

    public function test_a_saved_image_color_can_change_and_be_cleared(): void
    {
        $this->storeProduct(['gallery' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]])->assertSessionHasNoErrors();
        $product = $this->latestParentProduct();
        [$a, $b] = $product->getMedia('product_gallery')->modelKeys();

        $this->updateProduct($product, ['gallery_colors' => [$a => $this->blue->id, $b => $this->red->id]])->assertSessionHasNoErrors();
        $this->assertSame([$this->blue->id, $this->red->id], $this->colors($product));

        $this->updateProduct($product, ['gallery_colors' => [$a => null]])->assertSessionHasNoErrors();
        $this->assertSame([null, $this->red->id], $this->colors($product));
    }

    public function test_a_value_that_is_not_a_color_is_saved_as_no_color(): void
    {
        $size = Attribute::factory()->create(['type' => 1]);
        $large = AttributeValue::factory()->create(['attribute_id' => $size->id, 'name' => 'L']);

        $this->storeProduct(['gallery' => [UploadedFile::fake()->image('a.jpg')], 'gallery_new_colors' => [$large->id]])->assertSessionHasNoErrors();
        $product = $this->latestParentProduct();
        $this->assertSame([null], $this->colors($product));

        $media = $product->getFirstMedia('product_gallery');
        $this->updateProduct($product, ['gallery_colors' => [$media->id => 999999]])->assertSessionHasNoErrors();
        $this->assertSame([null], $this->colors($product));
    }

    public function test_a_deleted_color_does_not_block_a_save(): void
    {
        $this->storeProduct([
            'manage_stock' => true,
            'stock_quantity' => 5,
            'gallery' => [UploadedFile::fake()->image('red.jpg')],
            'gallery_new_colors' => [$this->red->id],
        ])->assertSessionHasNoErrors();
        $product = $this->latestParentProduct();
        $media = $product->getFirstMedia('product_gallery');
        $this->assertSame([$this->red->id], $this->colors($product));

        $deleted = $this->red->id;
        $this->red->delete();

        // What the Edit page sends back: the saved color it was opened with, and a stock change.
        $this->updateProduct($product, [
            'manage_stock' => true,
            'stock_quantity' => 8,
            'stock_quantity_loaded' => 5,
            'gallery_colors' => [$media->id => $deleted],
        ])->assertSessionHasNoErrors();

        $this->assertSame(8, (int) Product::query()->findOrFail($product->id)->stock_quantity);
        $this->assertSame([null], $this->colors($product), 'the image shows no color any more');
    }

    public function test_another_products_media_is_never_touched(): void
    {
        $other = Product::factory()->create();
        $foreign = $other->addMedia(UploadedFile::fake()->image('o.jpg'))->toMediaCollection('product_gallery', 'items');
        $product = Product::factory()->create();
        $product->categories()->attach($this->category->id);

        $this->updateProduct($product, ['gallery_colors' => [$foreign->id => $this->red->id]])->assertSessionHasNoErrors();

        $this->assertNull($foreign->fresh()->getCustomProperty('attribute_value_id'));
    }

    public function test_the_storefront_sends_each_image_with_its_color(): void
    {
        $this->storeProduct([
            'slug' => 'colored-shirt',
            'gallery' => [UploadedFile::fake()->image('red.jpg'), UploadedFile::fake()->image('plain.jpg')],
            'gallery_new_colors' => [$this->red->id, null],
        ])->assertSessionHasNoErrors();

        URL::defaults(['locale' => 'ar']);
        app(StorefrontCatalogCache::class)->forget();

        $this->get($this->siteUrl('/ar/product/colored-shirt'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('product.images', 3)
            ->where('product.images.0.color', null)
            ->where('product.images.1.color', $this->red->id)
            ->where('product.images.2.color', null)
            ->has('product.images.1.url'));
    }

    /**
     * @return list<?int>
     */
    private function colors(Product $product): array
    {
        return Product::query()->findOrFail($product->id)->getMedia('product_gallery')
            ->map(fn ($media) => $media->getCustomProperty('attribute_value_id'))->values()->all();
    }
}
