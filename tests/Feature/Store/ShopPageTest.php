<?php

namespace Tests\Feature\Store;

use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;

class ShopPageTest extends StorefrontTestCase
{
    private const SHOP = 'Store/Storefront/Shop';

    public function test_lists_published_products_with_prices(): void
    {
        Product::factory()->create(['name' => 'ساعة يد', 'base_price' => 300, 'sale_price' => 240]);
        Product::factory()->draft()->create(['name' => 'مسودة']);

        $products = $this->props('/ar/shop', self::SHOP)['products'];

        $this->assertSame(['ساعة يد'], array_column($products['data'], 'name'));
        $this->assertSame('240.00 ₪', $products['data'][0]['price']['label']);
        $this->assertSame('300.00 ₪', $products['data'][0]['price']['was']);
        $this->assertSame('منتج واحد', $products['total_label']);
    }

    public function test_category_route_filters_and_unknown_or_inactive_is_404(): void
    {
        $bags = Category::factory()->create(['name' => 'حقائب', 'slug' => 'bags']);
        Category::factory()->create(['slug' => 'hidden', 'status' => CategoryStatus::INACTIVE]);
        Product::factory()->create(['name' => 'نظارة']);
        $bag = Product::factory()->create(['name' => 'حقيبة جلد']);
        $bag->categories()->attach($bags->id);
        $bag->touch();

        $props = $this->props('/ar/category/bags', 'Store/Storefront/Category');
        $this->assertSame('حقائب', $props['category']['name']);
        $this->assertSame(['حقيبة جلد'], array_column($props['products']['data'], 'name'));

        $this->get($this->tenantUrl('/ar/category/hidden'))->assertNotFound();
        $this->get($this->tenantUrl('/ar/category/nothing'))->assertNotFound();
    }

    public function test_search_and_sort_update_the_list(): void
    {
        Product::factory()->create(['name' => 'عطر ورد', 'base_price' => 90]);
        Product::factory()->create(['name' => 'عطر عود', 'base_price' => 150]);
        Product::factory()->create(['name' => 'شمعة']);

        $names = fn (string $query) => array_column($this->props('/ar/shop?'.$query, self::SHOP)['products']['data'], 'name');

        $this->assertEqualsCanonicalizing(['عطر ورد', 'عطر عود'], $names('q=%D8%B9%D8%B7%D8%B1'));
        $this->assertSame(['عطر عود', 'عطر ورد'], $names('q=%D8%B9%D8%B7%D8%B1&sort=price_desc'));
        $this->assertSame(['عطر ورد', 'عطر عود'], $names('q=%D8%B9%D8%B7%D8%B1&sort=price_asc'));
        $this->assertSame('newest', $this->props('/ar/shop?sort=bogus', self::SHOP)['filters']['sort']);
    }

    public function test_search_ignores_letter_case(): void
    {
        Product::factory()->create(['name' => 'Cotton Shirt']);
        Product::factory()->create(['name' => 'شمعة']);

        $this->assertSame(['Cotton Shirt'], array_column($this->props('/ar/shop?q=cOTTON', self::SHOP)['products']['data'], 'name'));
    }

    public function test_sorted_first_pages_are_cached_apart(): void
    {
        Product::factory()->create(['name' => 'رخيص', 'base_price' => 10]);
        Product::factory()->create(['name' => 'غال', 'base_price' => 100]);

        $names = fn (string $query) => array_column($this->props('/ar/shop'.$query, self::SHOP)['products']['data'], 'name');

        foreach ([1, 2] as $visit) {
            $this->assertSame(['رخيص', 'غال'], $names('?sort=price_asc'));
            $this->assertSame(['غال', 'رخيص'], $names('?sort=price_desc'));
        }
    }

    public function test_pagination_links(): void
    {
        Product::factory()->count(25)->create();

        $first = $this->props('/ar/shop?sort=price_asc', self::SHOP)['products'];
        $this->assertSame(2, $first['last_page']);
        $this->assertNull($first['prev_url']);
        $this->assertStringContainsString('page=2', $first['next_url']);
        $this->assertStringContainsString('sort=price_asc', $first['next_url']); // the filters stay

        $second = $this->props('/ar/shop?sort=price_asc&page=2', self::SHOP)['products'];
        $this->assertCount(1, $second['data']);
        $this->assertNotNull($second['prev_url']);
        $this->assertNull($second['next_url']);
    }

    public function test_an_array_or_broken_query_does_not_break_the_page(): void
    {
        $this->get($this->tenantUrl('/ar/shop?q[]=x'))->assertOk();
        $this->get($this->tenantUrl('/ar?q[]=x'))->assertOk();
        $this->get($this->tenantUrl('/ar/shop?sort[]=x&page[]=2'))->assertOk();
        $this->get($this->tenantUrl('/ar/shop?q=%00'))->assertOk();
        $this->get($this->tenantUrl('/ar/shop?q=%FF'))->assertOk();
    }

    public function test_a_page_past_the_end_says_so(): void
    {
        Product::factory()->create();

        $products = $this->props('/ar/shop?page=9', self::SHOP)['products'];

        $this->assertSame([], $products['data']);
        $this->assertSame(1, $products['total']);
    }

    public function test_empty_state(): void
    {
        $products = $this->props('/ar/shop', self::SHOP)['products'];
        $this->assertSame([], $products['data']);
        $this->assertSame(0, $products['total']);

        $this->assertSame('لاشيء', $this->props('/ar/shop?q=%D9%84%D8%A7%D8%B4%D9%8A%D8%A1', self::SHOP)['filters']['q']);
    }

    public function test_listing_falls_back_to_the_other_locale(): void
    {
        Product::factory()->create(['name' => 'منتج بالعربي فقط']);

        $this->assertSame(['منتج بالعربي فقط'], array_column($this->props('/en/shop', self::SHOP)['products']['data'], 'name'));
    }
}
