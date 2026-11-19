<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\TierPrice;

/**
 * A product's wholesale terms in the admin: each item's price per tier, quantity breaks, and sold by
 * the piece or in size runs. Reached from the product list's row menu.
 */
class ProductPricePagesTest extends WholesaleTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_the_list(): void
    {
        [$shirt] = $this->shirt();
        $belt = $this->single(['wholesale_price' => null]);
        QuantityBreak::query()->create(['product_id' => $shirt->id, 'min_quantity' => 10, 'discount_percent' => 5]);

        $this->page('/prices')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/ProductPrice/Index')
            ->where('table.meta.total', 2)
            ->etc());

        $only = fn (string $query, int $id) => $this->page('/prices?'.$query)->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $id)->etc());

        $only('filter[has_wholesale_price]=1', $shirt->id);
        $only('filter[has_wholesale_price]=0', $belt->id);
        $only('filter[has_breaks]=1', $shirt->id);
        $only('filter[name]=حزام', $belt->id);
    }

    public function test_the_edit_page_lists_items_and_tiers(): void
    {
        [$shirt, $variants] = $this->shirt();
        $gold = $this->tier('ذهبي', 10);
        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $gold->id, 'price' => 39]);

        $this->page('/prices/'.$shirt->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/ProductPrice/Edit')
            ->where('product.wholesale_price', 50)
            ->where('product.by_piece', true)
            ->has('items', 6)
            ->where('items.0.name', 'أسود / S')
            ->where('tiers.0.name', 'ذهبي')
            ->where('prices.'.$variants['black-S']->id.'.'.$gold->id, 39)
            ->etc());
    }

    public function test_a_product_without_variations_is_its_own_item(): void
    {
        $belt = $this->single();
        $gold = $this->tier('ذهبي', 10);
        TierPrice::query()->create(['product_id' => $belt->id, 'tier_id' => $gold->id, 'price' => 12]);

        $this->page('/prices/'.$belt->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $belt->id)
            ->where('prices.'.$belt->id.'.'.$gold->id, 12)
            ->etc());
    }

    public function test_saving_overrides_breaks_and_by_piece(): void
    {
        [$shirt, $variants] = $this->shirt();
        $gold = $this->tier('ذهبي', 10);
        TierPrice::query()->create(['product_id' => $variants['white-L']->id, 'tier_id' => $gold->id, 'price' => 30]);

        $this->actingAs($this->admin)->put($this->url('/prices/'.$shirt->id), [
            'by_piece' => false,
            'prices' => [
                $variants['black-S']->id => [$gold->id => 44.5],
                $variants['white-L']->id => [$gold->id => ''],
            ],
            'breaks' => [
                ['min_quantity' => 24, 'discount_percent' => 10],
                ['min_quantity' => 12, 'discount_percent' => 5],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) $shirt->fresh()->wholesale_by_piece);
        $this->assertSame([[$variants['black-S']->id, '44.50']], TierPrice::query()->get()->map(fn ($row) => [$row->product_id, $row->price])->all());
        $this->assertSame([[12, '5.00'], [24, '10.00']], QuantityBreak::query()->orderBy('min_quantity')->get()->map(fn ($row) => [$row->min_quantity, $row->discount_percent])->all());

        $this->actingAs($this->admin)->put($this->url('/prices/'.$shirt->id), [
            'by_piece' => true,
            'breaks' => [['min_quantity' => 12, 'discount_percent' => 5], ['min_quantity' => 12, 'discount_percent' => 6]],
        ])->assertSessionHasErrors('breaks.1.min_quantity');
    }

    public function test_a_break_cannot_give_the_goods_away(): void
    {
        [$shirt] = $this->shirt();

        $this->actingAs($this->admin)->put($this->url('/prices/'.$shirt->id), ['by_piece' => true, 'breaks' => [['min_quantity' => 12, 'discount_percent' => 100]]])
            ->assertSessionHasErrors('breaks.0.discount_percent');
        $this->assertSame(0, QuantityBreak::query()->count());
    }

    public function test_breaks_must_rise(): void
    {
        [$shirt] = $this->shirt();
        $save = fn (array $breaks) => $this->actingAs($this->admin)->put($this->url('/prices/'.$shirt->id), ['by_piece' => true, 'breaks' => $breaks]);

        // More pieces for the same or a smaller discount: refused, in whatever order they are sent.
        $save([['min_quantity' => 12, 'discount_percent' => 10], ['min_quantity' => 24, 'discount_percent' => 5]])->assertSessionHasErrors('breaks.1.discount_percent');
        $save([['min_quantity' => 24, 'discount_percent' => 10], ['min_quantity' => 12, 'discount_percent' => 10]])->assertSessionHasErrors('breaks.0.discount_percent');
        $this->assertSame(0, QuantityBreak::query()->count());

        $save([['min_quantity' => 24, 'discount_percent' => 10], ['min_quantity' => 12, 'discount_percent' => 5]])->assertSessionHasNoErrors();
        $this->assertSame(2, QuantityBreak::query()->count());
    }

    public function test_an_item_of_another_product_is_refused(): void
    {
        [$shirt] = $this->shirt();
        $belt = $this->single();
        $gold = $this->tier('ذهبي', 10);

        $this->actingAs($this->admin)->put($this->url('/prices/'.$shirt->id), [
            'by_piece' => true,
            'prices' => [$belt->id => [$gold->id => 10]],
        ])->assertSessionHasErrors('prices');

        $this->assertSame(0, TierPrice::query()->count());
    }

    public function test_the_product_row_links_here(): void
    {
        $this->actingAs($this->admin)->get($this->siteUrl('/ar/admin/products'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rowLinks.0.route', 'wholesale-prices.edit')
            ->where('rowLinks.0.permission', 'Wholesale Price Update')
            ->etc());
    }

    public function test_every_action_needs_its_permission(): void
    {
        [$shirt] = $this->shirt();
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Wholesale Price View List');

        $this->actingAs($viewer)->get($this->url('/prices'))->assertOk();
        $this->actingAs($viewer)->get($this->url('/prices/'.$shirt->id.'/edit'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/prices/'.$shirt->id), ['by_piece' => false])->assertForbidden();

        $this->assertTrue((bool) $shirt->fresh()->wholesale_by_piece);
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin/wholesale'.$path);
    }

    private function page(string $path)
    {
        return $this->actingAs($this->admin)->get($this->url($path));
    }
}
