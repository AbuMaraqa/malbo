<?php

namespace Tests\Feature\Wholesale;

use Modules\Items\Entities\Category;
use Modules\Items\Enums\ProductStatus;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\TierPrice;

/**
 * The wholesale catalog: published products sold wholesale to the merchant's tier, with search, a
 * category filter, the tier price ("from" when sizes differ), the suggested retail price and whether
 * anything is available.
 */
class CatalogTest extends WholesaleTestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant(attributes: ['tier_id' => $this->tier('ذهبي', 10)->id]);
    }

    public function test_only_products_sold_wholesale(): void
    {
        [$shirt] = $this->shirt();
        $belt = $this->single();
        $this->single(['wholesale_price' => null, 'name' => 'بلا جملة']);
        $this->single(['status' => ProductStatus::DRAFT]);
        $onlyGold = $this->single(['wholesale_price' => null, 'name' => 'ذهبي فقط']);
        TierPrice::query()->create(['product_id' => $onlyGold->id, 'tier_id' => $this->merchant->tier_id, 'price' => 12]);
        $otherTier = $this->single(['wholesale_price' => null, 'name' => 'شريحة أخرى']);
        TierPrice::query()->create(['product_id' => $otherTier->id, 'tier_id' => $this->tier('فضي', 0)->id, 'price' => 12]);

        $ids = array_column($this->catalog()['products']['data'], 'id');
        sort($ids);

        $expected = [$shirt->id, $belt->id, $onlyGold->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_cards_show_tier_price_from_and_suggested_retail(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['white-L']->update(['wholesale_price' => 60]);
        $variants['black-S']->update(['sale_price' => 80]);

        $card = collect($this->catalog()['products']['data'])->firstWhere('id', $shirt->id);

        $this->assertSame('45.00 ₪', $card['price_label']);   // 50 - 10 %
        $this->assertTrue($card['price_from']);              // white L: 60 - 10 % = 54
        $this->assertSame('80.00 ₪', $card['retail_label']); // the lowest storefront price
        $this->assertTrue($card['available']);
        $this->assertSame(route('store.wholesale.product', ['slug' => $shirt->slug]), $card['url']);
    }

    public function test_an_out_of_stock_product_shows_as_unavailable(): void
    {
        $belt = $this->single(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);

        $card = collect($this->catalog()['products']['data'])->firstWhere('id', $belt->id);

        $this->assertFalse($card['available']);
    }

    public function test_search_and_category(): void
    {
        [$shirt] = $this->shirt();
        $belt = $this->single(['sku' => 'BELT-001']);
        $category = Category::factory()->create(['name' => 'أحزمة', 'slug' => 'belts', 'status' => 1]);
        $belt->categories()->attach($category->id);

        $this->assertSame([$shirt->id], array_column($this->catalog('?q=قميص')['products']['data'], 'id'));
        $this->assertSame([$belt->id], array_column($this->catalog('?q=belt-0')['products']['data'], 'id'));
        $this->assertSame([$belt->id], array_column($this->catalog('?category=belts')['products']['data'], 'id'));
    }

    public function test_pages_are_filtered_before_they_are_cut(): void
    {
        foreach (range(1, 24) as $index) {
            $this->single(['name' => 'منتج '.$index]);
        }

        // Newer, so first in the list, but not sellable: its only variant is hidden, or inactive.
        [$hidden, $hiddenVariants] = $this->shirt();
        foreach ($hiddenVariants as $variant) {
            $variant->attributeValues()->update(['is_active' => false]);
        }
        [$inactive, $inactiveVariants] = $this->shirt();
        foreach ($inactiveVariants as $variant) {
            $variant->update(['is_active' => 0]);
        }

        $page = $this->catalog()['products'];

        $this->assertSame(24, $page['total']);
        $this->assertCount(24, $page['data']);
        $this->assertSame(1, $page['last_page']);
        $this->assertNotContains($hidden->id, array_column($page['data'], 'id'));
        $this->assertNotContains($inactive->id, array_column($page['data'], 'id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(string $query = ''): array
    {
        $this->actingAs($this->merchant->user);

        return $this->props('/ar/wholesale'.$query, 'Wholesale/Portal/Catalog');
    }
}
