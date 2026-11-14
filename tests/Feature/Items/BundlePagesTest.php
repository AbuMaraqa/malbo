<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\BundleType;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesBundles;

/**
 * Sets and bundle offers in the admin: their own list and form, components in order, a fixed price
 * or a percentage off the components. A bundle holds no stock and is sold on the storefront only.
 */
class BundlePagesTest extends DatabaseTestCase
{
    use MakesBundles;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');
        Storage::fake('items');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');
    }

    public function test_the_list_filters_and_permissions(): void
    {
        [$shirt] = $this->sized('قميص', ['M' => 3]);
        $set = $this->bundle([[$shirt, 1]], ['name' => 'طقم صيفي']);
        $offer = $this->bundle([[$shirt, 2]], ['name' => 'عرض قميصين'], BundleType::BUNDLE);

        $this->page('/bundles')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Bundle/Index')
            ->where('table.meta.total', 2)
            ->where('table.data.0.id', $offer->id)
            ->where('table.data.0.type', 'bundle')
            ->where('table.data.0.components_count', 1)
            ->where('table.data.1.name', 'طقم صيفي'));

        $this->page('/bundles?filter[bundle_type]=set')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $set->id));
        $this->page('/bundles?filter[name]=عرض')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $offer->id));

        // The component picker offers products and variants, never another bundle.
        $found = $this->actingAs($this->staff)->getJson($this->url('/bundles/lookup?q=قميص'))->assertOk()->json();
        $this->assertContains($shirt->id, array_column($found, 'value'));
        $this->assertNotContains($set->id, array_column($found, 'value'));

        $nobody = User::forceCreate(['name' => 'N', 'email' => 'n-'.Str::random(6).'@items.test', 'password' => 'secret-password', 'email_verified_at' => now()])->givePermissionTo('Product View List');
        $this->actingAs($nobody)->get($this->url('/bundles'))->assertForbidden();
        $this->actingAs($nobody)->get($this->url('/bundles/add'))->assertForbidden();
        $this->actingAs($nobody)->delete($this->url('/bundles/'.$set->id))->assertForbidden();
    }

    public function test_add_saves_the_bundle_its_components_and_inert_stock(): void
    {
        [$shirt] = $this->sized('قميص', ['M' => 3]);
        [$trousers, $sizes] = $this->sized('بنطال', ['32' => 2]);
        $category = Category::factory()->create();

        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload([
            'name' => 'طقم الصيف',
            'slug' => 'Summer Set',
            'category_id' => [$category->id],
            'components' => [['product_id' => $trousers->id, 'quantity' => 1], ['product_id' => $shirt->id, 'quantity' => 2], ['product_id' => $sizes['32']->id, 'quantity' => 1]],
            'image' => UploadedFile::fake()->image('set.jpg', 600, 800),
        ]))->assertSessionHasNoErrors()->assertRedirect($this->url('/bundles'));

        $bundle = Product::query()->with('bundleComponents')->where('bundle_type', 'set')->latest('id')->firstOrFail();
        $this->assertSame('طقم الصيف', $bundle->translate('ar')->name);
        $this->assertSame('summer-set', $bundle->slug);
        $this->assertSame([[$trousers->id, 1], [$shirt->id, 2], [$sizes['32']->id, 1]], $bundle->bundleComponents->map(fn ($c) => [$c->product_id, $c->quantity])->all());
        $this->assertSame([$category->id], $bundle->categories()->get()->modelKeys());
        $this->assertSame('250.00', $bundle->base_price);
        $this->assertTrue((bool) $bundle->manage_stock);
        $this->assertSame(0, (int) $bundle->stock_quantity);
        $this->assertSame('out_of_stock', $bundle->stock_status);
        $this->assertNotNull($bundle->getFirstMedia('product'));
        $this->assertSame(0, $bundle->variations()->count());

        $this->page('/bundles/'.$bundle->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Bundle/Edit')
            ->where('bundle.name', 'طقم الصيف')
            ->where('bundle.pricing', 'fixed')
            ->where('bundle.components.1.product_id', $shirt->id)
            ->where('bundle.components.1.quantity', 2)
            ->has('selectedComponents', 3));

        // The products list and form are for products: a bundle opens its own form.
        $this->page('/products/'.$bundle->id.'/edit')->assertRedirect($this->url('/bundles/'.$bundle->id.'/edit'));

        $this->actingAs($this->staff)->post($this->url('/bundles/'.$bundle->id), $this->payload([
            '_method' => 'put',
            'name' => 'طقم الصيف',
            'slug' => 'summer-set',
            'components' => [['product_id' => $shirt->id, 'quantity' => 1]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$shirt->id], $bundle->bundleComponents()->pluck('product_id')->all());

        $this->actingAs($this->staff)->delete($this->url('/bundles/'.$bundle->id))->assertSessionHasNoErrors();
        $this->assertNull(Product::withTrashed()->find($bundle->id));
        $this->assertNotNull($shirt->fresh(), 'deleting a bundle never deletes its components');
    }

    public function test_percent_mode_prices_from_the_components(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3, 'L' => 3], 120);
        $shirts['L']->update(['base_price' => 130]);
        $trousers = Product::factory()->create(['base_price' => 200, 'sale_price' => 180, 'manage_stock' => false]);

        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload([
            'bundle_type' => 'bundle',
            'pricing' => 'percent',
            'bundle_discount_percent' => 20,
            'base_price' => null,
            'components' => [['product_id' => $shirt->id, 'quantity' => 1], ['product_id' => $trousers->id, 'quantity' => 1]],
        ]))->assertSessionHasNoErrors();

        $bundle = Product::query()->where('bundle_type', 'bundle')->latest('id')->firstOrFail();
        // The regular prices: the shirt's lowest size (120), the trousers' sale price (180).
        $this->assertSame('300.00', $bundle->base_price);
        $this->assertSame('percentage', $bundle->discount_type);
        $this->assertSame('20.00', $bundle->discount_value);
        $this->assertSame('20.00', $bundle->bundle_discount_percent);
        $this->assertNull($bundle->sale_price);
    }

    public function test_a_component_price_change_refreshes_percent_bundles(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3], 120);
        $trousers = Product::factory()->create(['base_price' => 200, 'manage_stock' => false]);

        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload([
            'pricing' => 'percent',
            'bundle_discount_percent' => 10,
            'components' => [['product_id' => $shirt->id, 'quantity' => 2], ['product_id' => $trousers->id, 'quantity' => 1]],
        ]))->assertSessionHasNoErrors();
        $bundle = Product::query()->where('bundle_type', 'set')->latest('id')->firstOrFail();
        $this->assertSame('440.00', $bundle->base_price);

        $trousers->update(['base_price' => 150]);
        $this->assertSame('390.00', $bundle->fresh()->base_price);

        $shirts['M']->update(['base_price' => 100]);
        $this->assertSame('350.00', $bundle->fresh()->base_price, 'a variant\'s price moves the bundles of its product');

        $fixed = $this->bundle([[$trousers, 1]], ['base_price' => 99]);
        $trousers->update(['base_price' => 10]);
        $this->assertSame('99.00', $fixed->fresh()->base_price, 'a fixed price never moves');
    }

    public function test_validation_errors(): void
    {
        [$shirt] = $this->sized('قميص', ['M' => 3]);
        $other = $this->bundle([[$shirt, 1]]);

        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['components' => []]))->assertSessionHasErrors('components');
        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['components' => [['product_id' => $other->id, 'quantity' => 1]]]))->assertSessionHasErrors('components.0.product_id');
        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['components' => [['product_id' => $shirt->id, 'quantity' => 0]]]))->assertSessionHasErrors('components.0.quantity');
        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['pricing' => 'percent', 'bundle_discount_percent' => 95]))->assertSessionHasErrors('bundle_discount_percent');
        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['base_price' => null]))->assertSessionHasErrors('base_price');
        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['bundle_type' => 'other']))->assertSessionHasErrors('bundle_type');
        $this->actingAs($this->staff)->post($this->url('/bundles'), $this->payload(['name' => '']))->assertSessionHasErrors('name');

        $this->assertSame(1, Product::query()->whereNotNull('bundle_type')->count());
    }

    public function test_bundles_stay_out_of_products_stock_and_wholesale(): void
    {
        $belt = Product::factory()->create(['name' => 'حزام', 'manage_stock' => true, 'stock_quantity' => 1, 'wholesale_price' => 10]);
        $bundle = $this->bundle([[$belt, 1]], ['name' => 'حزام مع هدية', 'wholesale_price' => 50, 'status' => ProductStatus::PUBLISHED]);
        $products = app(ProductRepositoryInterface::class);

        $this->page('/products')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $belt->id));
        $this->assertFalse($products->isSellable($bundle->id));
        $this->assertNotContains($bundle->id, $products->searchSellable('حزام')->modelKeys());
        $this->assertNotContains($bundle->id, $products->lowStockQuery()->pluck('id')->all());

        $wholesale = app(WholesaleProductRepositoryInterface::class);
        $this->assertNotContains($bundle->id, $wholesale->adminTableQuery()->pluck('id')->all());
        $this->assertNotContains($bundle->id, collect($wholesale->catalog(null, [])->items())->pluck('id')->all());
        $this->assertNull($wholesale->findPublished($bundle->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'bundle_type' => 'set',
            'name' => 'Summer set',
            'short_description' => 'قميص وبنطال',
            'description' => '<p>طقم كامل</p><script>alert(1)</script>',
            'slug' => '',
            'sku' => '',
            'status' => ProductStatus::PUBLISHED->value,
            'is_active' => true,
            'is_new' => false,
            'is_featured' => false,
            'category_id' => [],
            'pricing' => 'fixed',
            'base_price' => 250,
            'sale_price' => null,
            'bundle_discount_percent' => null,
            'max_quantity_per_order' => null,
            'components' => [['product_id' => Product::factory()->create(['manage_stock' => false])->id, 'quantity' => 1]],
            ...$overrides,
        ];
    }

    private function page(string $path)
    {
        return $this->actingAs($this->staff)->get($this->url($path));
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin'.$path);
    }
}
