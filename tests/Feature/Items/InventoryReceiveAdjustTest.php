<?php

namespace Tests\Feature\Items;

use Illuminate\Validation\ValidationException;
use Modules\Core\Repositories\Contracts\DashboardRepositoryInterface;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Services\InventoryService;
use Modules\Store\Services\ProductConfigurator;
use Tests\DatabaseTestCase;

/**
 * Receiving goods adds to the stock (INCOMING), an adjustment moves it by a signed amount with a
 * reason (ADJUST). Neither sets an absolute level, and an adjustment never takes non-backorder
 * stock below zero.
 */
class InventoryReceiveAdjustTest extends DatabaseTestCase
{
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = app(InventoryService::class);
    }

    public function test_receive_adds_stock_as_incoming(): void
    {
        $product = $this->product(3);

        $this->inventory->receive($product, 5, 'Shipment from the factory', 1);

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame('in_stock', $product->fresh()->stock_status);
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();
        $this->assertSame([StockMovementType::INCOMING, 5, 8, 'Shipment from the factory', 1], [$movement->type, $movement->quantity, $movement->balance_after, $movement->note, $movement->created_by]);
    }

    public function test_receive_refuses_zero_or_negative(): void
    {
        $product = $this->product(3);

        foreach ([0, -2] as $quantity) {
            try {
                $this->inventory->receive($product, $quantity, null, null);
                $this->fail("{$quantity} must be refused.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('quantity', $e->errors());
            }
        }

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_receive_refuses_a_product_with_variants(): void
    {
        $parent = $this->product(0);
        Product::factory()->variationOf($parent)->create(['manage_stock' => true, 'stock_quantity' => 1]);

        $this->expectException(ValidationException::class);

        $this->inventory->receive($parent, 2, null, null);
    }

    public function test_adjust_moves_by_the_delta_with_the_reason(): void
    {
        $product = $this->product(10);

        $this->inventory->adjust($product, -3, 'Damaged in the store', 1);
        $this->inventory->adjust($product, 1, 'Found on a shelf', 1);

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $movements = StockMovement::query()->where('product_id', $product->id)->orderBy('id')->get();
        $this->assertSame([-3, 1], $movements->pluck('quantity')->all());
        $this->assertSame(['Damaged in the store', 'Found on a shelf'], $movements->pluck('note')->all());
        $this->assertTrue($movements->every(fn (StockMovement $movement) => $movement->type === StockMovementType::ADJUST));
    }

    public function test_adjust_refuses_going_below_zero_without_backorder(): void
    {
        $product = $this->product(2);

        try {
            $this->inventory->adjust($product, -3, 'Count', null);
            $this->fail('Below zero must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delta', $e->errors());
        }

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->where('product_id', $product->id)->count());

        $this->inventory->adjust($product, -2, 'Count', null);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame('out_of_stock', $product->fresh()->stock_status);
    }

    public function test_adjust_may_go_below_zero_with_backorder(): void
    {
        $product = $this->product(1, ['allow_backorder' => true]);

        $this->inventory->adjust($product, -3, 'Count', null);

        $this->assertSame(-2, $product->fresh()->stock_quantity);
        $this->assertSame('on_backorder', $product->fresh()->stock_status);
    }

    public function test_adjust_needs_a_delta_and_a_reason(): void
    {
        $product = $this->product(5);

        foreach ([[0, 'Count', 'delta'], [2, '  ', 'reason']] as [$delta, $reason, $field]) {
            try {
                $this->inventory->adjust($product, $delta, $reason, null);
                $this->fail("{$field} must be refused.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_an_unmanaged_product_stays_sellable_after_an_adjustment(): void
    {
        $product = $this->product(0, ['manage_stock' => false, 'stock_status' => 'in_stock']);

        $this->inventory->adjust($product, -1, 'Count', null);
        $this->inventory->receive($product->fresh(), 1, null, null);
        $this->inventory->adjust($product->fresh(), -2, 'Count', null);

        $fresh = $product->fresh();
        $this->assertSame(-2, (int) $fresh->stock_quantity, 'the number typed is kept');
        $this->assertSame('in_stock', $fresh->stock_status, 'stock that is not managed never changes the status');
        $this->assertTrue(ProductConfigurator::available($fresh));
    }

    public function test_low_stock_query(): void
    {
        $low = $this->product(2, ['low_stock_threshold' => 2]);
        $out = $this->product(0, ['low_stock_threshold' => 0]);
        $fine = $this->product(5, ['low_stock_threshold' => 2]);
        $unmanaged = $this->product(0, ['manage_stock' => false, 'low_stock_threshold' => 2]);
        $parent = $this->product(0, ['low_stock_threshold' => 5]);
        $variant = Product::factory()->variationOf($parent)->create(['manage_stock' => true, 'stock_quantity' => 1, 'low_stock_threshold' => 3]);

        $ids = app(ProductRepositoryInterface::class)->lowStockQuery()->pluck('id')->all();

        $this->assertContains($low->id, $ids);
        $this->assertContains($out->id, $ids);
        $this->assertContains($variant->id, $ids);
        $this->assertNotContains($fine->id, $ids);
        $this->assertNotContains($unmanaged->id, $ids);
        $this->assertNotContains($parent->id, $ids, 'a product with variants keeps its stock on them');
    }

    public function test_low_stock_counts_only_products_for_sale(): void
    {
        $low = ['stock_quantity' => 0, 'low_stock_threshold' => 2];
        $forSale = $this->product(0, $low);
        $draft = $this->product(0, [...$low, 'status' => ProductStatus::DRAFT]);
        $inactive = $this->product(0, [...$low, 'is_active' => 0]);

        $variantOf = fn (array $parent, array $variant = []) => Product::factory()->variationOf($this->product(0, $parent))
            ->create(['manage_stock' => true, ...$low, ...$variant]);
        $sellableVariant = $variantOf([]);
        $draftParent = $variantOf(['status' => ProductStatus::DRAFT]);
        $inactiveParent = $variantOf(['is_active' => 0]);
        $inactiveVariant = $variantOf([], ['is_active' => 0]);

        $ids = app(ProductRepositoryInterface::class)->lowStockQuery()->pluck('id')->all();

        $this->assertContains($forSale->id, $ids);
        $this->assertContains($sellableVariant->id, $ids);
        foreach (['draft' => $draft, 'inactive' => $inactive, 'variant of a draft' => $draftParent, 'variant of an inactive product' => $inactiveParent, 'inactive variant' => $inactiveVariant] as $what => $product) {
            $this->assertNotContains($product->id, $ids, $what);
        }

        // The dashboard's card counts the same products.
        $this->assertSame(count($ids), app(DashboardRepositoryInterface::class)->lowStockCount());
    }

    private function product(int $stock, array $attributes = []): Product
    {
        return Product::factory()->create(['manage_stock' => true, 'stock_quantity' => $stock, 'low_stock_threshold' => 0, ...$attributes]);
    }
}
