<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\BranchPrice;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Events\ProductUpdated;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Services\ProductService;
use Tests\TenantTestCase;

class ProductServiceTest extends TenantTestCase
{
    private ProductService $service;

    private ProductRepositoryInterface $products;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->service = app(ProductService::class);
        $this->products = app(ProductRepositoryInterface::class);
        $this->user = User::forceCreate(['name' => 'Staff', 'email' => 'staff-'.Str::random(10).'@items.test', 'password' => 'password']);
        $this->category = Category::factory()->create();
    }

    public function test_create_writes_everything_in_one_transaction(): void
    {
        $branch = Branch::factory()->create();
        $addon = Addon::factory()->create();
        $option = AddonOption::factory()->for($addon)->create();

        $product = $this->service->create($this->data([
            'tags' => ['وسم'],
            'branches' => [$branch->id],
            'addons' => [['addon_id' => $addon->id, 'is_required' => true, 'max_selection' => 2,
                'allowed_options' => [['addon_option_id' => $option->id, 'price' => 4]]]],
            'variations' => [['name' => 'أحمر', 'slug' => 'red-'.Str::random(4), 'base_price' => 110, 'is_featured' => 0, 'is_digital' => 0]],
        ]), 'ar', $this->user->id);

        $this->assertSame('منتج', $product->fresh()->translate('ar')->name);
        $this->assertSame($this->user->id, (int) $product->created_by);
        $this->assertSame(ProductStatus::PUBLISHED, $product->fresh()->status);
        $this->assertSame(1, $product->tags()->count());
        $this->assertSame([$this->category->id], $product->categories()->pluck(config('items.table_prefix').'categories.id')->all());
        $this->assertSame([$branch->id], $product->branches()->pluck('branch_id')->all());
        $this->assertSame(1, DB::table(config('items.table_prefix').'addon_product')->where('product_id', $product->id)->count());
        $this->assertSame(1, $product->variations()->count());
        $this->assertSame(ProductStatus::PUBLISHED, $product->variations()->first()->status);
    }

    public function test_failed_create_leaves_no_rows(): void
    {
        $slug = 'broken-'.Str::random(6);

        try {
            $this->service->create($this->data([
                'slug' => $slug,
                'variations' => [
                    ['name' => 'أحمر', 'slug' => 'red-'.Str::random(4), 'base_price' => 110, 'is_featured' => 0, 'is_digital' => 0],
                    // A category that does not exist: the pivot insert fails after the parent and one variation exist.
                    ['name' => 'أزرق', 'slug' => 'blue-'.Str::random(4), 'base_price' => 110, 'is_featured' => 0, 'is_digital' => 0, 'category_id' => [999999999]],
                ],
            ]), 'ar', $this->user->id);
            $this->fail('The create should have failed.');
        } catch (\Illuminate\Database\QueryException) {
            // expected
        }

        $this->assertFalse(Product::withTrashed()->where('slug', $slug)->exists());
        $this->assertFalse(Product::withTrashed()->whereTranslation('name', 'أحمر')->exists());
    }

    public function test_update_in_another_locale_keeps_the_first(): void
    {
        $product = $this->service->create($this->data(), 'ar', $this->user->id);

        $this->service->update($product, ['name' => 'English name', 'base_price' => 150], 'en', $this->user->id);

        $fresh = $product->fresh();
        $this->assertSame('منتج', $fresh->translate('ar')->name);
        $this->assertSame('English name', $fresh->translate('en')->name);
        $this->assertEquals(150, $fresh->base_price);
    }

    public function test_set_status_cascades(): void
    {
        $parent = Product::factory()->published()->create();
        $variation = Product::factory()->variationOf($parent)->create();

        $this->service->setStatus($parent, ProductStatus::DRAFT);

        $this->assertSame(ProductStatus::DRAFT, $parent->fresh()->status);
        $this->assertSame(ProductStatus::DRAFT, $variation->fresh()->status);
    }

    public function test_branch_prices_save_and_clear_for_variations(): void
    {
        $parent = Product::factory()->create();
        $variation = Product::factory()->variationOf($parent)->create();
        $branch = Branch::factory()->create();

        $this->service->saveBranchPrices($parent, [$parent->id => [$branch->id => 90], $variation->id => [$branch->id => 95]]);
        $this->assertEquals(95, BranchPrice::where('product_id', $variation->id)->where('branch_id', $branch->id)->value('price'));

        $this->service->saveBranchPrices($parent, [$variation->id => [$branch->id => '']]);
        $this->assertFalse(BranchPrice::where('product_id', $variation->id)->exists());
        $this->assertTrue(BranchPrice::where('product_id', $parent->id)->exists());
    }

    public function test_published_for_storefront(): void
    {
        $shown = Product::factory()->published()->create();
        $draft = Product::factory()->draft()->create();
        $inactive = Product::factory()->published()->create(['is_active' => 0]);
        $trashed = Product::factory()->published()->create();
        $trashed->delete();
        $variation = Product::factory()->variationOf($shown)->create();

        $ids = $this->products->publishedForStorefront()->pluck('id');

        $this->assertTrue($ids->contains($shown->id));
        foreach ([$draft, $inactive, $trashed, $variation] as $hidden) {
            $this->assertFalse($ids->contains($hidden->id), "product {$hidden->id}");
        }
    }

    public function test_unique_slug_counts_trashed_products(): void
    {
        $trashed = Product::factory()->create(['slug' => 'taken-slug']);
        $trashed->delete();

        $this->assertSame('taken-slug-1', $this->products->uniqueSlug('taken-slug'));
        $this->assertSame('taken-slug', $this->products->uniqueSlug('taken-slug', $trashed->id));
        $this->assertSame('free-slug', $this->products->uniqueSlug('Free Slug'));
    }

    public function test_update_with_a_blank_sku_keeps_the_stored_one(): void
    {
        // The form says "keep empty for auto generated"; the column is NOT NULL.
        $product = Product::factory()->create(['sku' => 'SKU-KEEP']);

        $this->service->update($product, ['sku' => ''], 'ar', $this->user->id);

        $this->assertSame('SKU-KEEP', $product->fresh()->sku);
    }

    public function test_media_accepts_a_single_file_and_the_form_state(): void
    {
        $single = $this->service->create($this->data(['image' => \Illuminate\Http\UploadedFile::fake()->image('a.jpg')]), 'ar', $this->user->id);
        $state = $this->service->create($this->data(['image' => ['uuid-1' => \Illuminate\Http\UploadedFile::fake()->image('b.jpg')]]), 'ar', $this->user->id);

        $this->assertCount(1, $single->fresh()->getMedia('product'));
        $this->assertCount(1, $state->fresh()->getMedia('product'));
    }

    public function test_branch_prices_only_touch_the_product_its_variations_and_real_branches(): void
    {
        $parent = Product::factory()->create();
        $variation = Product::factory()->variationOf($parent)->create();
        $unrelated = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->saveBranchPrices($parent, [
            $variation->id => [$branch->id => 50, 999999999 => 60],
            $unrelated->id => [$branch->id => 70],
        ]);

        $this->assertTrue(BranchPrice::where('product_id', $variation->id)->where('branch_id', $branch->id)->exists());
        $this->assertFalse(BranchPrice::where('branch_id', 999999999)->exists());
        $this->assertFalse(BranchPrice::where('product_id', $unrelated->id)->exists());
    }

    public function test_create_dispatches_product_updated_once(): void
    {
        Event::fake([ProductUpdated::class]);

        $product = $this->service->create($this->data(), 'ar', $this->user->id);
        $this->service->update($product, ['base_price' => 1], 'ar', $this->user->id);

        Event::assertDispatchedTimes(ProductUpdated::class, 1);
    }

    private function data(array $overrides = []): array
    {
        return [
            'name' => 'منتج',
            'slug' => 'product-'.Str::lower(Str::random(6)),
            'base_price' => 100,
            'is_featured' => 0,
            'is_digital' => 0,
            'category_id' => [$this->category->id],
            ...$overrides,
        ];
    }
}
