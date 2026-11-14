<?php

namespace Tests\Feature\Store;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;

/**
 * The header's suggestions while typing: a few products and categories as JSON.
 */
class SearchSuggestTest extends StorefrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(md5('store-search'.'store-search-ip:127.0.0.1'));
    }

    public function test_it_needs_two_characters(): void
    {
        Product::factory()->create(['name' => 'قميص']);

        $this->suggest('ق')->assertOk()->assertExactJson(['products' => [], 'categories' => []]);
        $this->suggest('  ')->assertOk()->assertExactJson(['products' => [], 'categories' => []]);
        $this->assertCount(1, $this->suggest('قم')->json('products'));
    }

    public function test_it_returns_up_to_six_products_and_four_categories(): void
    {
        Product::factory()->count(8)->create(['name' => 'قميص قطن']);
        Category::factory()->count(5)->create(['name' => 'قمصان']);

        $response = $this->suggest('قم')->assertOk();

        $this->assertCount(6, $response->json('products'));
        $this->assertCount(4, $response->json('categories'));
    }

    public function test_a_product_carries_name_image_price_and_url(): void
    {
        Storage::fake('items');
        $exact = Product::factory()->create(['name' => 'Polo', 'slug' => 'polo', 'base_price' => 200, 'sale_price' => 150]);
        $exact->addMedia(UploadedFile::fake()->image('polo.jpg'))->toMediaCollection('product', 'items');
        Product::factory()->create(['name' => 'Classic polo shirt', 'slug' => 'classic-polo']);
        $category = Category::factory()->create(['name' => 'Polo shirts', 'slug' => 'polos']);

        $response = $this->suggest('POLO')->assertOk();

        $first = $response->json('products.0');
        $this->assertSame('Polo', $first['name']); // the exact name first
        $this->assertSame('150.00 ₪', $first['price']);
        $this->assertSame('200.00 ₪', $first['was']);
        $this->assertSame(route('store.product', ['slug' => 'polo']), $first['url']);
        $this->assertNotNull($first['image']);
        $this->assertNull($response->json('products.1.image'));
        $this->assertSame([['name' => 'Polo shirts', 'url' => route('store.category', ['slug' => $category->slug])]], $response->json('categories'));
    }

    public function test_hidden_products_and_inactive_categories_are_left_out(): void
    {
        Product::factory()->draft()->create(['name' => 'قميص مسودة']);
        Product::factory()->create(['name' => 'قميص معطل', 'is_active' => 0]);
        $parent = Product::factory()->create(['name' => 'بنطال']);
        Product::factory()->variationOf($parent)->create(['name' => 'قميص متغير']);
        Category::factory()->create(['name' => 'قمصان مخفية', 'status' => CategoryStatus::INACTIVE]);

        $this->suggest('قميص')->assertOk()->assertExactJson(['products' => [], 'categories' => []]);
    }

    public function test_it_is_throttled_per_visitor_and_per_address(): void
    {
        $request = Request::create('/ar/search/suggest', server: ['REMOTE_ADDR' => '10.0.0.7']);
        $session = app('session')->driver();
        $session->setId(str_repeat('a', 40));
        $request->setLaravelSession($session);

        $limits = RateLimiter::limiter('store-search')($request);

        $this->assertSame([120, 300], array_map(fn (Limit $limit) => $limit->maxAttempts, $limits));
        $this->assertSame(['store-search:10.0.0.7|'.str_repeat('a', 40), 'store-search-ip:10.0.0.7'], array_map(fn (Limit $limit) => $limit->key, $limits));

        // Every test request opens a new session: the address alone stops at 300.
        for ($i = 0; $i < 300; $i++) {
            $this->suggest('ab')->assertOk();
        }

        $this->suggest('ab')->assertStatus(429);
    }

    public function test_suggestions_are_read_fresh(): void
    {
        $product = Product::factory()->create(['name' => 'قميص قديم']);
        $this->assertSame('قميص قديم', $this->suggest('قميص')->json('products.0.name'));

        // A write that skips the model events (an import): nothing renews a cache.
        DB::table($product->translations()->getRelated()->getTable())->where('product_id', $product->id)->update(['name' => 'قميص جديد']);

        $this->assertSame('قميص جديد', $this->suggest('قميص')->json('products.0.name'));
    }

    private function suggest(string $q)
    {
        return $this->getJson($this->siteUrl('/ar/search/suggest?q='.rawurlencode($q)));
    }
}
