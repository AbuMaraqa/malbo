<?php

namespace Tests\Feature\Store;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Tests\Feature\Items\Concerns\MakesBundles;

/**
 * "Complete the look": staff put other products next to a product; its page shows them with a quick
 * way to add a chosen size to the cart. Nothing is shown when none are set.
 */
class CompleteTheLookTest extends StorefrontTestCase
{
    use MakesBundles;

    private Product $jacket;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jacket = Product::factory()->create(['name' => 'جاكيت', 'slug' => 'jacket', 'manage_stock' => false]);
        $this->staff = $this->staff(['Product View List', 'Product Update']);
    }

    public function test_staff_link_products_in_order(): void
    {
        [$shirt] = $this->sized('قميص', ['M' => 2]);
        $belt = Product::factory()->create(['name' => 'حزام']);

        $this->actingAs($this->staff)->put($this->admin('/products/'.$this->jacket->id.'/look'), ['products' => [$belt->id, $shirt->id]])->assertSessionHasNoErrors();

        $this->assertSame([$belt->id, $shirt->id], $this->jacket->looks()->get()->modelKeys());

        $this->actingAs($this->staff)->put($this->admin('/products/'.$this->jacket->id.'/look'), ['products' => [$shirt->id]])->assertSessionHasNoErrors();
        $this->assertSame([$shirt->id], $this->jacket->looks()->get()->modelKeys());

        $found = $this->actingAs($this->staff)->getJson($this->admin('/products/look-lookup?q=حز'))->assertOk()->json();
        $this->assertSame([$belt->id], array_column($found, 'value'));

        $reader = $this->staff(['Product View List']);
        $this->actingAs($reader)->put($this->admin('/products/'.$this->jacket->id.'/look'), ['products' => []])->assertForbidden();
    }

    public function test_only_other_non_bundle_parents_up_to_eight(): void
    {
        [$shirt, $sizes] = $this->sized('قميص', ['M' => 2]);
        $set = $this->bundle([[$shirt, 1]]);
        $put = fn (array $ids) => $this->actingAs($this->staff)->put($this->admin('/products/'.$this->jacket->id.'/look'), ['products' => $ids]);

        $put([$this->jacket->id])->assertSessionHasErrors('products.0');
        $put([$sizes['M']->id])->assertSessionHasErrors('products.0');
        $put([$set->id])->assertSessionHasErrors('products.0');
        $put(Product::factory()->count(9)->create()->modelKeys())->assertSessionHasErrors('products');

        $this->assertSame(0, $this->jacket->looks()->count());
    }

    public function test_the_page_shows_the_look_with_its_sizes(): void
    {
        [$shirt, $sizes] = $this->sized('قميص', ['M' => 2, 'L' => 0], 150);
        $belt = Product::factory()->create(['name' => 'حزام', 'base_price' => 40, 'manage_stock' => false]);
        $hidden = Product::factory()->create(['name' => 'مخفي', 'status' => ProductStatus::DRAFT]);
        $this->jacket->looks()->attach([$shirt->id => ['sort_order' => 0], $hidden->id => ['sort_order' => 1], $belt->id => ['sort_order' => 2]]);

        $look = $this->props('/ar/product/jacket', 'Store/Storefront/ProductPage')['look'];

        $this->assertSame(['قميص', 'حزام'], array_column($look, 'name'));
        $this->assertSame([['id' => $sizes['M']->id, 'label' => 'M', 'in_stock' => true], ['id' => $sizes['L']->id, 'label' => 'L', 'in_stock' => false]], $look[0]['variants']);
        $this->assertSame('150.00 ₪', $look[0]['price']['label']);
        $this->assertTrue($look[0]['in_stock']);
        $this->assertSame([], $look[1]['variants']);
        $this->assertSame(route('store.product', ['slug' => $belt->slug]), $look[1]['url']);

        $this->actingAs($this->staff)->get($this->admin('/products/'.$this->jacket->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('look.0.value', $shirt->id)
            ->where('look.1.label', 'مخفي'));
    }

    public function test_none_set_shows_nothing(): void
    {
        $this->assertSame([], $this->props('/ar/product/jacket', 'Store/Storefront/ProductPage')['look']);
    }

    private function admin(string $path): string
    {
        return $this->siteUrl('/ar/admin'.$path);
    }
}
