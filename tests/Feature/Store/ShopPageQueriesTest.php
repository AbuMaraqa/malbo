<?php

namespace Tests\Feature\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;

/**
 * The shop page with search, filters and facets reads a fixed number of queries, however many
 * products, variants and values it shows, and loads no relation lazily.
 */
class ShopPageQueriesTest extends StorefrontTestCase
{
    private Attribute $color;

    private Attribute $size;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->color = Attribute::factory()->create(['slug' => 'color', 'type' => AttributeType::COLOR->value, 'is_filterable' => true]);
        $this->size = Attribute::factory()->create(['slug' => 'size', 'is_filterable' => true, 'is_size' => true]);
        $this->category = Category::factory()->create(['slug' => 'shirts']);
    }

    public function test_the_filtered_shop_page_reads_a_fixed_number_of_queries(): void
    {
        $this->get($this->siteUrl($this->url()))->assertOk(); // the layout's own caches, which products do not renew
        $this->products(2, 2);
        $few = $this->queries();

        $this->products(10, 4);
        $many = $this->queries();

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(30, $many);
    }

    public function test_the_filtered_shop_page_lazy_loads_nothing(): void
    {
        $this->products(3, 3);
        $lazy = [];
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$lazy): void {
            $lazy[] = $model::class.'::'.$relation;
        });

        $this->get($this->siteUrl($this->url()))->assertOk();
        $this->get($this->siteUrl('/ar/shop?q=%D9%82%D9%85%D9%8A%D8%B5&sort=best_selling'))->assertOk();

        $this->assertSame([], $lazy);
    }

    private function url(): string
    {
        $black = AttributeValue::query()->where('attribute_id', $this->color->id)->orderBy('id')->value('id');

        return '/ar/category/shirts?q=%D9%82%D9%85%D9%8A%D8%B5&attr['.$this->color->id.']='.$black.'&price_min=1&price_max=500&sort=price_asc';
    }

    /**
     * Queries of one filtered category page, the cache store's own aside.
     */
    private function queries(): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count): void {
            $count += str_contains($query->sql, 'from "cache"') || str_contains($query->sql, 'into "cache"') ? 0 : 1;
        });

        $this->get($this->siteUrl($this->url()))->assertOk();

        $counted = $count;
        $count = -1_000_000; // later listeners' queries no longer count

        return $counted;
    }

    /**
     * $count shirts in the category, each with a label and $values colors × 2 sizes as variants.
     */
    private function products(int $count, int $values): void
    {
        $colors = $this->values($this->color, $values);
        $sizes = $this->values($this->size, 2);
        $label = Label::factory()->create();

        foreach (range(1, $count) as $i) {
            $product = Product::factory()->create(['name' => 'قميص '.$i, 'base_price' => 100]);
            $product->categories()->attach($this->category->id);
            $product->labels()->attach($label->id);

            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    Product::factory()->variationOf($product)->create(['manage_stock' => true, 'stock_quantity' => 3])
                        ->attributeValues()->attach([$color->id, $size->id]);
                }
            }
        }
    }

    /**
     * @return list<AttributeValue>
     */
    private function values(Attribute $attribute, int $count): array
    {
        $existing = AttributeValue::query()->where('attribute_id', $attribute->id)->orderBy('id')->get()->all();

        while (count($existing) < $count) {
            $existing[] = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'is_active' => true, 'sort_order' => count($existing)]);
        }

        return $existing;
    }
}
