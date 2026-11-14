<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Services\InventoryService;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * The stock pages: the ledger of every movement, what runs low, receiving goods and correcting
 * the stock by hand.
 */
class StockPagesTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();
    }

    public function test_movements_are_shaped_for_the_table(): void
    {
        [$shirt, $variant] = $this->shirtWithVariant(5);
        $this->storeOrder([['product_id' => $variant->id, 'quantity' => 2]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/stock/movements'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Stock/Movements')
            ->where('table.data.0.product_name', 'قميص أسود L')
            ->where('table.data.0.sku', 'SHIRT-BLK-L')
            ->where('table.data.0.edit_product_id', $shirt->id)
            ->where('table.data.0.type', 'sale')
            ->where('table.data.0.quantity', -2)
            ->where('table.data.0.balance_after', 3)
            ->where('table.data.0.order_id', $order->id)
            ->where('table.data.0.order_number', $order->order_number)
            ->where('table.data.0.by', 'Staff')
            ->has('types', 5));
    }

    public function test_movement_filters(): void
    {
        $linen = $this->stocked(10, ['name' => 'Linen shirt', 'sku' => 'LIN-1']);
        $cotton = $this->stocked(10, ['name' => 'Cotton shirt', 'sku' => 'COT-1']);
        app(InventoryService::class)->receive($linen, 3, 'Factory', $this->staff->id);
        app(InventoryService::class)->adjust($cotton, -1, 'Damaged', $this->staff->id);
        $this->storeOrder([['product_id' => $cotton->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $ids = fn (string $query) => collect($this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/stock/movements?'.$query))
            ->viewData('page')['props']['table']['data'])->pluck('id')->all();

        $incoming = StockMovement::query()->where('type', 'incoming')->where('product_id', $linen->id)->value('id');
        $adjust = StockMovement::query()->where('type', 'adjust')->where('product_id', $cotton->id)->value('id');
        $sale = StockMovement::query()->where('type', 'sale')->value('id');

        $this->assertContains($incoming, $ids('filter[product]=linen'));
        $this->assertNotContains($adjust, $ids('filter[product]=linen'));
        $this->assertSame([$adjust], array_values(array_intersect($ids('filter[product]=cot-1'), [$incoming, $adjust])));
        $this->assertSame([$adjust], $ids('filter[type]=adjust&filter[product_id]='.$cotton->id));
        $this->assertSame([$sale], $ids('filter[order]='.$order->order_number));
        $this->assertSame([], $ids('filter[created_at_from]=2999-01-01'));
    }

    public function test_low_stock_lists_what_is_at_or_below_its_threshold(): void
    {
        $low = $this->stocked(2, ['name' => 'Low', 'low_stock_threshold' => 3]);
        $out = $this->stocked(0, ['name' => 'Out', 'low_stock_threshold' => 1]);
        $this->stocked(9, ['name' => 'Fine', 'low_stock_threshold' => 3]);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/stock/low'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Stock/LowStock')
            ->where('table.meta.total', 2)
            ->where('table.data.0.id', $out->id)
            ->where('table.data.0.stock', 0)
            ->where('table.data.1.id', $low->id)
            ->where('table.data.1.threshold', 3)
            ->has('categories'));
    }

    public function test_low_stock_filters(): void
    {
        $category = Category::factory()->create();
        [$shirt, $variant] = $this->shirtWithVariant(1);
        $variant->update(['low_stock_threshold' => 2]);
        $shirt->categories()->attach($category->id);
        $out = $this->stocked(0, ['low_stock_threshold' => 1]);

        $ids = fn (string $query) => collect($this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/stock/low?'.$query))
            ->viewData('page')['props']['table']['data'])->pluck('id')->all();

        $this->assertSame([$variant->id], $ids('filter[category]='.$category->id), 'a variant is in its parent\'s categories');
        $this->assertSame([$out->id], $ids('filter[out_of_stock]=1'));
        $this->assertSame([$variant->id], $ids('filter[out_of_stock]=0'));
    }

    public function test_receive_goods(): void
    {
        $product = $this->stocked(4);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/stock/receive'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Items/Stock/Receive'));

        $this->actingAs($this->staff)->from($this->siteUrl('/ar/admin/stock/receive'))->post($this->siteUrl('/ar/admin/stock/receive'), [
            'product_id' => $product->id,
            'quantity' => 6,
            'note' => 'Delivery note 123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $movement = StockMovement::query()->where('product_id', $product->id)->latest('id')->first();
        $this->assertSame([StockMovementType::INCOMING, 6, 'Delivery note 123', $this->staff->id], [$movement->type, $movement->quantity, $movement->note, $movement->created_by]);

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/stock/receive'), ['product_id' => $product->id, 'quantity' => 0])
            ->assertSessionHasErrors('quantity');
    }

    public function test_adjust_stock(): void
    {
        $product = $this->stocked(4);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/stock/adjust?product='.$product->id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Items/Stock/Adjust')->where('selected.value', $product->id)->where('selected.stock', 4));

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/stock/adjust'), [
            'product_id' => $product->id,
            'delta' => -3,
            'reason' => 'Stock count',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $product->fresh()->stock_quantity);

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/stock/adjust'), ['product_id' => $product->id, 'delta' => 2])
            ->assertSessionHasErrors('reason');
    }

    public function test_an_adjustment_below_zero_shows_the_message(): void
    {
        $product = $this->stocked(1);

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/stock/adjust'), ['product_id' => $product->id, 'delta' => -2, 'reason' => 'Count'])
            ->assertSessionHasErrors('delta');

        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public function test_the_product_lookup_lists_sellable_products(): void
    {
        [$shirt, $variant] = $this->shirtWithVariant(3);

        $this->actingAs($this->staff)->getJson($this->siteUrl('/ar/admin/stock/products?q=shirt-blk'))->assertOk()
            ->assertJsonPath('0.value', $variant->id)
            ->assertJsonPath('0.stock', 3);

        $values = collect($this->actingAs($this->staff)->getJson($this->siteUrl('/ar/admin/stock/products?q=قميص'))->json())->pluck('value')->all();
        $this->assertNotContains($shirt->id, $values, 'a product with variants keeps its stock on them');
    }

    public function test_every_action_needs_its_permission(): void
    {
        $product = $this->stocked(4);
        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Stock View List');

        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/stock/movements'))->assertOk();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/stock/low'))->assertOk();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/stock/receive'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl('/ar/admin/stock/receive'), ['product_id' => $product->id, 'quantity' => 1])->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/stock/adjust'))->assertForbidden();
        $this->actingAs($viewer)->post($this->siteUrl('/ar/admin/stock/adjust'), ['product_id' => $product->id, 'delta' => 1, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/stock/products'))->assertForbidden();

        // Staff (another permission) without the stock ones.
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Order View List');
        $this->actingAs($other)->get($this->siteUrl('/ar/admin/stock/movements'))->assertForbidden();
        $this->actingAs($other)->get($this->siteUrl('/ar/admin/stock/low'))->assertForbidden();

        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_super_admin_has_the_permissions_and_the_sidebar_group(): void
    {
        $role = Role::findByName('Super Admin');
        $this->assertTrue($role->hasPermissionTo('Stock View List'));
        $this->assertTrue($role->hasPermissionTo('Stock Update'));

        $this->actingAs($this->staff);
        $group = collect(app(SidebarService::class)->all())->firstWhere('title', 'Inventory');
        $this->assertSame(['stock.movements', 'stock.low', 'stock.receive', 'stock.adjust'], array_column($group['children'], 'route'));
    }
}
