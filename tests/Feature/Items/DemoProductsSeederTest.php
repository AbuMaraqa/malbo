<?php

namespace Tests\Feature\Items;

use Modules\Items\database\seeders\DemoProductsSeeder;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Tests\DatabaseTestCase;

class DemoProductsSeederTest extends DatabaseTestCase
{
    public function test_every_category_gets_ten_published_products_once(): void
    {
        $shoes = Category::factory()->create(['name' => 'الأحذية']);
        $other = Category::factory()->create(['name' => 'مجموعة الصيف']);

        $this->seed(DemoProductsSeeder::class);
        $this->seed(DemoProductsSeeder::class);

        foreach ([$shoes, $other] as $category) {
            $this->assertSame(10, $category->products()->count());
        }
        $this->assertSame(20, app(ProductRepositoryInterface::class)->publishedForStorefront()->count());

        $first = Product::query()->where('slug', "demo-c{$shoes->id}-1")->firstOrFail();
        $this->assertStringStartsWith('حذاء', $first->translate('ar')->name);
        $this->assertStringStartsWith('Lightweight Walking Shoe', $first->translate('en')->name);
        $this->assertSame(10 + 7, (int) $first->stock_quantity);
        // Every third piece is on offer.
        $this->assertNotNull(Product::query()->where('slug', "demo-c{$shoes->id}-3")->value('sale_price'));
    }

    public function test_a_store_without_categories_gets_the_malbo_six(): void
    {
        $this->assertSame(0, Category::query()->count());

        $this->seed(DemoProductsSeeder::class);

        $this->assertSame(6, Category::query()->count());
        $this->assertSame(60, Product::query()->count());
    }

    public function test_the_kind_comes_from_the_category_name(): void
    {
        $this->assertSame('shoes', DemoProductsSeeder::kindOf('الأحذية'));
        $this->assertSame('hoodies', DemoProductsSeeder::kindOf('فوتر'));
        $this->assertSame('jackets', DemoProductsSeeder::kindOf('Jackets'));
        $this->assertNull(DemoProductsSeeder::kindOf('مجموعة الصيف'));
    }
}
