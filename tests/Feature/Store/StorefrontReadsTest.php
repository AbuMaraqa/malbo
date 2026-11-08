<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Tests\DatabaseTestCase;

class StorefrontReadsTest extends DatabaseTestCase
{
    private ProductRepositoryInterface $products;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->products = app(ProductRepositoryInterface::class);
    }

    public function test_listing_shows_only_published_active_parent_products(): void
    {
        $visible = Product::factory()->create();
        Product::factory()->draft()->create();
        Product::factory()->create(['is_active' => 0]);
        Product::factory()->create()->delete();
        Product::factory()->variationOf($visible)->create();

        $this->assertSame([$visible->id], $this->ids($this->products->storefrontListing([])));
    }

    public function test_category_filter_includes_child_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'clothes']);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $inParent = $this->productIn($parent);
        $inChild = $this->productIn($child);
        Product::factory()->create();

        $ids = $this->ids($this->products->storefrontListing(['category' => 'clothes']));

        $this->assertEqualsCanonicalizing([$inParent->id, $inChild->id], $ids);
    }

    public function test_category_filter_skips_inactive_child_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'home-goods']);
        $hidden = Category::factory()->create(['parent_id' => $parent->id, 'status' => CategoryStatus::INACTIVE]);
        $this->productIn($hidden);

        $this->assertSame([], $this->ids($this->products->storefrontListing(['category' => 'home-goods'])));
    }

    public function test_cards_and_product_pages_only_carry_active_categories(): void
    {
        $hidden = Category::factory()->create(['status' => CategoryStatus::INACTIVE]);
        $product = $this->productIn($hidden);

        $this->assertTrue($this->products->storefrontListing([])->getCollection()->first()->categories->isEmpty());
        $this->assertTrue($this->products->findPublishedBySlug($product->slug)->categories->isEmpty());
    }

    public function test_search_matches_the_name_in_either_locale(): void
    {
        $product = Product::factory()->create(['name' => 'قميص قطن']);
        $product->translateOrNew('en')->name = 'Cotton shirt';
        $product->save();
        Product::factory()->create(['name' => 'حذاء']);

        $this->assertSame([$product->id], $this->ids($this->products->storefrontListing(['q' => 'قطن'])));
        $this->assertSame([$product->id], $this->ids($this->products->storefrontListing(['q' => 'cotton'])));
    }

    public function test_sort_orders(): void
    {
        $cheap = Product::factory()->create(['base_price' => 50]);
        $onSale = Product::factory()->create(['base_price' => 200, 'sale_price' => 30]);
        $dear = Product::factory()->create(['base_price' => 150, 'is_featured' => 1]);
        $this->age($cheap, 3);
        $this->age($onSale, 2);
        $this->age($dear, 1);

        $this->assertSame([$dear->id, $onSale->id, $cheap->id], $this->ids($this->products->storefrontListing(['sort' => 'newest'])));
        $this->assertSame([$onSale->id, $cheap->id, $dear->id], $this->ids($this->products->storefrontListing(['sort' => 'price_asc'])));
        $this->assertSame([$dear->id, $cheap->id, $onSale->id], $this->ids($this->products->storefrontListing(['sort' => 'price_desc'])));
        $this->assertSame($dear->id, $this->ids($this->products->storefrontListing(['sort' => 'featured']))[0]);
        $this->assertSame([$dear->id, $onSale->id, $cheap->id], $this->ids($this->products->storefrontListing(['sort' => 'nonsense'])));
    }

    public function test_listing_pages_by_24(): void
    {
        Product::factory()->count(25)->create();

        $page = $this->products->storefrontListing([]);

        $this->assertCount(24, $page->items());
        $this->assertSame(25, $page->total());
    }

    public function test_featured_falls_back_to_newest(): void
    {
        $featured = Product::factory()->create(['is_featured' => 1]);
        $old = Product::factory()->create();
        $new = Product::factory()->create();
        $this->age($featured, 5);
        $this->age($old, 4);
        Product::factory()->draft()->create(['is_featured' => 1]);

        $this->assertSame([$featured->id, $new->id], $this->products->featuredForStorefront(2)->pluck('id')->all());
    }

    public function test_find_published_by_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'shirt']);
        Product::factory()->draft()->create(['slug' => 'draft-shirt']);
        Product::factory()->variationOf($product)->create(['slug' => 'shirt-red']);

        $found = $this->products->findPublishedBySlug('shirt');

        $this->assertSame($product->id, $found?->id);
        $this->assertTrue($found->relationLoaded('variations'));
        $this->assertNull($this->products->findPublishedBySlug('draft-shirt'));
        $this->assertNull($this->products->findPublishedBySlug('shirt-red'));
    }

    public function test_related_shares_a_category_and_excludes_the_product(): void
    {
        $category = Category::factory()->create();
        $product = $this->productIn($category);
        $sibling = $this->productIn($category);
        $other = Product::factory()->create();

        $related = $this->products->relatedForStorefront($product, 4)->pluck('id')->all();

        $this->assertSame($sibling->id, $related[0]);
        $this->assertNotContains($product->id, $related);
        $this->assertContains($other->id, $related); // filled with newest when the category is small
    }

    public function test_find_active_category_by_slug(): void
    {
        Category::factory()->create(['slug' => 'shoes']);
        Category::factory()->create(['slug' => 'hidden', 'status' => CategoryStatus::INACTIVE]);

        $categories = app(CategoryRepositoryInterface::class);

        $this->assertSame('shoes', $categories->findActiveBySlug('shoes')?->slug);
        $this->assertNull($categories->findActiveBySlug('hidden'));
    }

    private function productIn(Category $category): Product
    {
        $product = Product::factory()->create();
        $product->categories()->attach($category->id);

        return $product;
    }

    private function age(Product $product, int $days): void
    {
        DB::table($product->getTable())->where('id', $product->id)->update(['created_at' => now()->subDays($days)]);
    }

    private function ids($page): array
    {
        return collect($page->items())->pluck('id')->all();
    }
}
