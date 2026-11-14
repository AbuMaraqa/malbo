<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\Event;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Events\ProductsBecameOrderable;
use Modules\Items\Services\InventoryService;
use Modules\Store\Services\ProductConfigurator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

/**
 * InventoryService tells the rest of the platform when an item that could not be ordered can be
 * ordered again (back-in-stock alerts listen), once per change, after the commit.
 */
class OrderableEventTest extends DatabaseTestCase
{
    public function test_the_event_fires_on_the_transition_only(): void
    {
        Event::fake([ProductsBecameOrderable::class]);
        $inventory = app(InventoryService::class);
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $other = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 4]);

        $inventory->receive($product, 2, null, null);
        Event::assertDispatchedTimes(ProductsBecameOrderable::class, 1);
        Event::assertDispatched(ProductsBecameOrderable::class, fn (ProductsBecameOrderable $event) => $event->productIds === [$product->id]);

        // Still orderable: no event.
        $inventory->receive($product, 2, null, null);
        $inventory->restock([$other->id => 1], StockMovementType::CANCEL);
        $inventory->sell([$product->id => 4]);
        Event::assertDispatchedTimes(ProductsBecameOrderable::class, 1);

        // Back from 0, with a manual correction this time.
        $inventory->adjust($product, 1, 'found one', null);
        Event::assertDispatchedTimes(ProductsBecameOrderable::class, 2);

        // A stock that is not managed is always orderable: nothing to announce.
        $unmanaged = Product::factory()->create(['manage_stock' => false, 'stock_quantity' => 0]);
        $inventory->setLevel($unmanaged, 5, StockMovementType::ADJUST);
        Event::assertDispatchedTimes(ProductsBecameOrderable::class, 2);
    }

    public function test_nothing_is_announced_when_the_change_rolls_back(): void
    {
        Event::fake([ProductsBecameOrderable::class]);
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock']);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($product): void {
                app(InventoryService::class)->receive($product, 3, null, null);

                throw new \RuntimeException('the order failed');
            });
        } catch (\RuntimeException) {
        }

        Event::assertNotDispatched(ProductsBecameOrderable::class);
    }

    #[DataProvider('stockStates')]
    public function test_the_rule_matches_the_storefront(bool $managed, bool $backorder, int $stock, string $status): void
    {
        $product = new Product;
        $product->forceFill(['manage_stock' => $managed, 'allow_backorder' => $backorder, 'stock_quantity' => $stock, 'stock_status' => $status]);

        $this->assertSame(ProductConfigurator::available($product), InventoryService::orderable($product));
    }

    /**
     * @return array<string, array{bool, bool, int, string}>
     */
    public static function stockStates(): array
    {
        return [
            'managed, in stock' => [true, false, 3, 'in_stock'],
            'managed, none' => [true, false, 0, 'out_of_stock'],
            'managed, backorder' => [true, true, 0, 'on_backorder'],
            'unmanaged' => [false, false, 0, 'in_stock'],
            'unmanaged, marked out' => [false, false, 9, 'out_of_stock'],
            'managed, marked out with stock' => [true, false, 5, 'out_of_stock'],
        ];
    }
}
