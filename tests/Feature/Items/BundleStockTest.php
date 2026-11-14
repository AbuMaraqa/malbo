<?php

namespace Tests\Feature\Items;

use Modules\Items\Entities\Product;
use Modules\Items\Support\BundleStock;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesBundles;

/**
 * A set or bundle holds no stock: what can be sold comes from its components, the size of each
 * chosen by the buyer.
 */
class BundleStockTest extends DatabaseTestCase
{
    use MakesBundles;

    private function load(Product $bundle): Product
    {
        return Product::query()->with(BundleStock::relations())->findOrFail($bundle->id);
    }

    public function test_available_is_the_min_over_components(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 3, 'L' => 7]);
        [$trousers] = $this->sized('بنطال', ['32' => 4, '34' => 1]);
        $belt = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 9]);

        $bundle = $this->load($this->bundle([[$shirt, 1], [$trousers, 1], [$belt, 2]]));

        // shirts: best size holds 7; trousers: best 4; belts: 9 / 2 = 4.
        $this->assertSame(4, BundleStock::available($bundle));
        $this->assertTrue(BundleStock::orderable($bundle));

        $shirts['L']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $shirts['M']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $bundle = $this->load($bundle);

        $this->assertSame(0, BundleStock::available($bundle));
        $this->assertFalse(BundleStock::orderable($bundle));
    }

    public function test_unmanaged_is_unlimited_and_a_deleted_component_blocks(): void
    {
        $free = Product::factory()->create(['manage_stock' => false]);
        $other = Product::factory()->create(['manage_stock' => false]);
        $bundle = $this->bundle([[$free, 1], [$other, 3]]);

        $this->assertNull(BundleStock::available($this->load($bundle)));
        $this->assertTrue(BundleStock::orderable($this->load($bundle)));

        $other->delete();
        $this->assertSame(0, BundleStock::available($this->load($bundle)));

        $other->restore();
        $other->update(['is_active' => 0]);
        $this->assertSame(0, BundleStock::available($this->load($bundle)), 'an inactive component blocks too');

        $this->assertSame(0, BundleStock::available($this->load($this->bundle([]))), 'a bundle without components is never sold');
    }

    public function test_max_for_aggregates_a_shared_variant(): void
    {
        [$shirt, $shirts] = $this->sized('قميص', ['M' => 5, 'L' => 1]);
        $bundle = $this->load($this->bundle([[$shirt, 1], [$shirt, 1]]));
        [$first, $second] = $bundle->bundleComponents->all();

        $resolved = BundleStock::resolve($bundle, [$first->id => $shirts['M']->id, $second->id => $shirts['M']->id]);
        $this->assertSame([], $resolved['missing']);
        $this->assertSame(2, BundleStock::maxFor($bundle, $resolved['chosen']), 'two shirts M per bundle out of 5');

        $resolved = BundleStock::resolve($bundle, [$first->id => $shirts['M']->id, $second->id => $shirts['L']->id]);
        $this->assertSame(1, BundleStock::maxFor($bundle, $resolved['chosen']));

        $resolved = BundleStock::resolve($bundle, [$first->id => $shirts['M']->id]);
        $this->assertSame([$second->id], array_map(fn ($component) => $component->id, $resolved['missing']));

        $stranger = Product::factory()->create();
        $resolved = BundleStock::resolve($bundle, [$first->id => $stranger->id, $second->id => $shirt->id]);
        $this->assertCount(2, $resolved['invalid'], 'only a variant of the component itself is a choice');
    }
}
