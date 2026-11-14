<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;

/**
 * Collections were merged into categories: the categories page, the old links, and the migration
 * that moved the data.
 */
class CategoriesPageTest extends StorefrontTestCase
{
    public function test_lists_active_root_categories_with_their_page_and_description(): void
    {
        Category::factory()->create(['name' => 'حقائب', 'slug' => 'bags', 'description' => 'جلد طبيعي', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'ساعات', 'slug' => 'watches', 'sort_order' => 1]);
        Category::factory()->create(['name' => 'مخفي', 'slug' => 'hidden', 'status' => CategoryStatus::INACTIVE]);

        $categories = $this->props('/ar/categories', 'Store/Storefront/Categories')['categories'];

        $this->assertSame(['ساعات', 'حقائب'], array_column($categories, 'name'));
        $this->assertSame('جلد طبيعي', $categories[1]['description']);
        $this->assertSame(route('store.category', ['slug' => 'bags']), $categories[1]['url']);
    }

    public function test_the_layout_links_the_categories_page_once_there_is_one(): void
    {
        $this->assertNull($this->props('/ar/shop', 'Store/Storefront/Shop')['store']['categories_url']);

        Category::factory()->create(['slug' => 'bags']);

        // Another page: a test reuses each route's controller, and with it the layout it already built.
        $this->assertSame(route('store.categories'), $this->props('/ar/contact', 'Store/Storefront/Contact')['store']['categories_url']);
    }

    public function test_the_sitemap_lists_the_categories_page(): void
    {
        $xml = $this->get($this->siteUrl('/sitemap.xml'))->assertOk()->getContent();

        $this->assertStringContainsString('/ar/categories<', $xml);
        $this->assertStringNotContainsString('/collections', $xml);
    }

    public function test_old_collection_links_redirect_for_good(): void
    {
        $this->get($this->siteUrl('/ar/collections'))->assertStatus(301)->assertRedirect(route('store.categories'));
        $this->get($this->siteUrl('/en/collections/summer'))->assertStatus(301)->assertRedirect(route('store.category', ['locale' => 'en', 'slug' => 'summer']));
    }

    public function test_a_category_page_shows_its_description_and_image_slot(): void
    {
        Category::factory()->create(['name' => 'حقائب', 'slug' => 'bags', 'description' => 'جلد طبيعي']);

        $category = $this->props('/ar/category/bags', 'Store/Storefront/Category')['category'];

        $this->assertSame('جلد طبيعي', $category['description']);
        $this->assertArrayHasKey('image', $category);
    }

    public function test_the_merge_migration_moves_collections_into_categories(): void
    {
        $prefix = config('items.table_prefix');
        $migration = require base_path('Modules/Items/database/migrations/2026_11_10_000001_merge_collections_into_categories.php');
        $migration->down(); // the old tables, empty

        $existing = Category::factory()->create(['slug' => 'linen']);
        $shirt = Product::factory()->create();
        $bag = Product::factory()->create();
        $existing->products()->attach($shirt->id);

        $summer = DB::table($prefix.'collections')->insertGetId(['slug' => 'summer', 'is_active' => false, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()]);
        $linen = DB::table($prefix.'collections')->insertGetId(['slug' => 'linen', 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table($prefix.'collection_translations')->insert([
            ['collection_id' => $summer, 'locale' => 'ar', 'name' => 'صيف', 'description' => 'خفيف'],
            ['collection_id' => $summer, 'locale' => 'en', 'name' => 'Summer', 'description' => null],
            ['collection_id' => $linen, 'locale' => 'ar', 'name' => 'كتان', 'description' => null],
        ]);
        DB::table($prefix.'collection_product')->insert([
            ['collection_id' => $summer, 'product_id' => $bag->id, 'sort_order' => 0],
            ['collection_id' => $linen, 'product_id' => $shirt->id, 'sort_order' => 0],
            ['collection_id' => $linen, 'product_id' => $bag->id, 'sort_order' => 1],
        ]);
        DB::table('permissions')->insert(['name' => 'Collection View List', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        // A new category for the new slug, with its names, order and state.
        $made = Category::where('slug', 'summer')->firstOrFail();
        $this->assertSame(CategoryStatus::INACTIVE, $made->status);
        $this->assertSame(7, $made->sort_order);
        $this->assertSame('صيف', $made->translate('ar')->name);
        $this->assertSame('خفيف', $made->translate('ar')->description);
        $this->assertSame('Summer', $made->translate('en')->name);
        $this->assertSame([$bag->id], $made->products()->pluck($prefix.'products.id')->all());

        // The same slug joins the category that has it: no product twice.
        $this->assertSame(1, Category::where('slug', 'linen')->count());
        $this->assertEqualsCanonicalizing([$shirt->id, $bag->id], $existing->products()->pluck($prefix.'products.id')->all());

        $this->assertFalse(Schema::hasTable($prefix.'collections'));
        $this->assertFalse(Schema::hasTable($prefix.'collection_product'));
        $this->assertFalse(DB::table('permissions')->where('name', 'like', 'Collection %')->exists());
    }
}
