<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Exceptions\InsufficientStock;
use Modules\Items\Services\InventoryService;
use Tests\DatabaseTestCase;

/**
 * The one place stock changes: every change is a movement, rows are locked, and a sale never takes
 * more than there is (unless the product allows backorders).
 */
class InventoryServiceTest extends DatabaseTestCase
{
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = app(InventoryService::class);
    }

    public function test_a_sale_takes_stock_and_writes_a_movement(): void
    {
        $product = $this->product(5);

        $this->inventory->sell([$product->id => 2]);

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();
        $this->assertSame(StockMovementType::SALE, $movement->type);
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(3, $movement->balance_after);
    }

    public function test_the_last_unit_cannot_be_sold_twice(): void
    {
        $product = $this->product(1);

        $this->inventory->sell([$product->id => 1]);

        try {
            $this->inventory->sell([$product->id => 1]);
            $this->fail('The second sale must be refused.');
        } catch (InsufficientStock $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_a_shortage_on_one_line_rolls_back_every_line(): void
    {
        $enough = $this->product(5);
        $short = $this->product(1);

        try {
            DB::transaction(fn () => $this->inventory->sell([$enough->id => 2, $short->id => 3]));
            $this->fail('The sale must be refused.');
        } catch (InsufficientStock) {
        }

        $this->assertSame(5, $enough->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_unmanaged_stock_is_left_alone(): void
    {
        $product = $this->product(0, ['manage_stock' => false]);

        $this->inventory->sell([$product->id => 4]);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_backorder_may_go_below_zero(): void
    {
        $product = $this->product(1, ['allow_backorder' => true]);

        $this->inventory->sell([$product->id => 3]);

        $fresh = $product->fresh();
        $this->assertSame(-2, $fresh->stock_quantity);
        $this->assertSame('on_backorder', $fresh->stock_status);
    }

    public function test_a_negative_sale_gives_stock_back(): void
    {
        $product = $this->product(5);

        $this->inventory->sell([$product->id => -2]);

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_restock_gives_stock_back(): void
    {
        $product = $this->product(0);

        $this->inventory->restock([$product->id => 2], StockMovementType::CANCEL);

        $fresh = $product->fresh();
        $this->assertSame(2, $fresh->stock_quantity);
        $this->assertSame('in_stock', $fresh->stock_status);
        $this->assertSame(StockMovementType::CANCEL, StockMovement::query()->sole()->type);
    }

    public function test_set_level_writes_the_difference(): void
    {
        $product = $this->product(10);

        $this->inventory->setLevel($product, 4, StockMovementType::ADJUST, 'جرد');

        $movement = StockMovement::query()->sole();
        $this->assertSame(-6, $movement->quantity);
        $this->assertSame(4, $movement->balance_after);
        $this->assertSame('جرد', $movement->note);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_setting_the_same_level_writes_nothing(): void
    {
        $product = $this->product(10);

        $this->inventory->setLevel($product, 10, StockMovementType::ADJUST);

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_crossing_the_threshold_is_reported_once(): void
    {
        $product = $this->product(5, ['low_stock_threshold' => 3]);

        $this->inventory->sell([$product->id => 2]);
        $this->assertSame([$product->id], $this->inventory->lowStock);

        $this->inventory->lowStock = [];
        $this->inventory->sell([$product->id => 1]);
        $this->assertSame([], $this->inventory->lowStock);
    }

    public function test_status_follows_the_stock(): void
    {
        $product = $this->product(1);

        $this->inventory->sell([$product->id => 1]);
        $this->assertSame('out_of_stock', $product->fresh()->stock_status);

        $this->inventory->restock([$product->id => 1], StockMovementType::RETURN);
        $this->assertSame('in_stock', $product->fresh()->stock_status);
    }

    private function product(int $stock, array $attributes = []): Product
    {
        return Product::factory()->create([
            'manage_stock' => true,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 0,
            ...$attributes,
        ]);
    }
}
