<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Tests\DatabaseTestCase;

/**
 * The catalog API answers the same after its reads moved to the repositories (Plan B, Task 7).
 * Written and run green against the controllers before the move.
 */
class ApiCatalogTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
    }

    public function test_api_products_filters_and_includes_unchanged(): void
    {
        $cheap = Product::factory()->create(['base_price' => 10, 'slug' => 'cheap-'.Str::lower(Str::random(5))]);
        $dear = Product::factory()->create(['base_price' => 90]);
        $variation = Product::factory()->variationOf($dear)->create();

        $this->getJson($this->siteUrl('/api/v1/items/products?filter[slug]='.$cheap->slug))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cheap->id);

        $sorted = collect($this->getJson($this->siteUrl('/api/v1/items/products?sort=-base_price&filter[parent_id]=null&per_page=100'))
            ->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($sorted->search($dear->id) < $sorted->search($cheap->id));
        $this->assertFalse($sorted->contains($variation->id));

        $withVariations = $this->getJson($this->siteUrl('/api/v1/items/products?filter[id]='.$dear->id.'&include=variations'))->assertOk();
        $this->assertSame([$variation->id], collect($withVariations->json('data.0.variations'))->pluck('id')->all());

        $this->getJson($this->siteUrl('/api/v1/items/products/'.$cheap->id))->assertOk()->assertJsonPath('data.id', $cheap->id);
    }

    public function test_api_product_attributes_and_addons_unchanged(): void
    {
        $parent = Product::factory()->create();
        $value = AttributeValue::factory()->for(Attribute::factory()->state(['type' => 3]))->create();
        Product::factory()->variationOf($parent)->create()->attributeValues()->sync([$value->id]);

        $attributes = $this->getJson($this->siteUrl('/api/v1/items/products/'.$parent->id.'/attributes'))->assertOk();
        $this->assertSame([$value->attribute_id], collect($attributes->json('data'))->pluck('id')->all());
        $this->assertSame([$value->id], collect($attributes->json('data.0.attribute_values') ?? $attributes->json('data.0.values') ?? [])->pluck('id')->all());

        $addon = Addon::factory()->create();
        $option = AddonOption::factory()->for($addon)->create(['price' => 5]);
        $pivotId = DB::table(config('items.table_prefix').'addon_product')->insertGetId([
            'addon_id' => $addon->id, 'product_id' => $parent->id, 'is_required' => 1, 'min_selection' => 1,
            'max_selection' => 1, 'max_quantity_per_option' => 1, 'created_by' => User::query()->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table(config('items.table_prefix').'addon_product_option')->insert([
            'addon_product_id' => $pivotId, 'addon_option_id' => $option->id, 'price' => 7, 'is_default' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $addons = $this->getJson($this->siteUrl('/api/v1/items/products/'.$parent->id.'/addons'))->assertOk();
        $this->assertSame([$addon->id], collect($addons->json('data'))->pluck('id')->all());
        $this->assertStringContainsString('7', json_encode($addons->json('data.0')));
    }

    public function test_api_categories_unchanged(): void
    {
        $category = Category::factory()->create();

        $ids = collect($this->getJson($this->siteUrl('/api/v1/items/categories?per_page=100'))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($category->id));

        $this->getJson($this->siteUrl('/api/v1/items/categories/'.$category->id))->assertOk()->assertJsonPath('data.id', $category->id);
    }

    public function test_sync_products_and_categories_unchanged(): void
    {
        $user = User::forceCreate(['name' => 'App', 'email' => 'app-'.Str::random(8).'@api.test', 'password' => 'password']);
        $old = Product::factory()->create();
        $old->forceFill(['updated_at' => '2020-01-01 00:00:00'])->saveQuietly();
        $new = Product::factory()->create();
        $category = Category::factory()->create();

        // Sync is for staff: the permissions of the products and categories pages.
        $user->givePermissionTo(['Product View List', 'Category View List']);
        $this->actingAs($user, 'sanctum');

        $products = collect($this->getJson($this->siteUrl('/api/v1/items/sync/products?since=2021-01-01 00:00:00'))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($products->contains($new->id));
        $this->assertFalse($products->contains($old->id));

        $categories = collect($this->getJson($this->siteUrl('/api/v1/items/sync/categories'))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($categories->contains($category->id));
    }
}
