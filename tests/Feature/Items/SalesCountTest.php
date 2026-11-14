<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Events\ProductSalesChanged;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\SalesCounter;
use Modules\Store\Services\StorefrontCatalogCache;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * `sales_count`: units on the lines of store orders (B2C) that hold their sale (not cancelled,
 * rejected or returned), counted on the parent product; wholesale volume never ranks the store. Kept by OrderService, rebuilt by
 * `items:recount-sales`, read by the shop's "best selling" sort.
 */
class SalesCountTest extends DatabaseTestCase
{
    use MakesOrders;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrders();
        $this->orders = app(OrderService::class);
        app(StorefrontCatalogCache::class)->forget();
    }

    public function test_store_orders_count_units_on_the_parent_wholesale_orders_do_not(): void
    {
        [$shirt, $black] = $this->shirtWithVariant(20);
        $white = Product::factory()->variationOf($shirt)->create(['manage_stock' => true, 'stock_quantity' => 20]);
        $belt = $this->stocked(20);

        $this->checkout([[$black, 2], [$belt, 1]]);
        $this->checkout([[$white, 3]], OrderChannel::B2B);
        $this->orders->place(['order_number' => 'API-1', 'payment_method' => PaymentMethod::CASH->value, 'delivery_address' => 'عنوان', 'contact_phone' => '0599', 'sub_total' => 100, 'total' => 100],
            [['product_id' => $black->id, 'quantity' => 1, 'price' => 100.0, 'total_price' => 100.0, 'notes' => null, 'options_to_attach' => []]], $this->customer->id);

        $this->assertSame(3, $shirt->fresh()->sales_count); // the wholesale 3 are not the store's best sellers
        $this->assertSame(1, $belt->fresh()->sales_count);
        $this->assertSame(0, $black->fresh()->sales_count); // variants hold no count
    }

    public function test_cancelling_or_rejecting_takes_the_units_back(): void
    {
        $product = $this->stocked(20);
        $cancelled = $this->checkout([[$product, 2]]);
        $rejected = $this->checkout([[$product, 3]]);
        $this->assertSame(5, $product->fresh()->sales_count);

        $this->orders->changeStatus($cancelled, OrderStatus::CANCELLED, $this->staff->id);
        $this->orders->changeStatus($rejected, OrderStatus::REJECTED, $this->staff->id);

        $this->assertSame(0, $product->fresh()->sales_count);
    }

    public function test_a_returned_order_takes_the_units_back_an_exchanged_one_keeps_them(): void
    {
        $product = $this->stocked(20);
        $returned = $this->delivered($this->checkout([[$product, 2]]));
        $exchanged = $this->delivered($this->checkout([[$product, 3]]));

        $this->orders->changeStatus($returned, OrderStatus::RETURNED, $this->staff->id);
        $this->orders->changeStatus($exchanged, OrderStatus::EXCHANGED, $this->staff->id, moveStock: false);

        $this->assertSame(3, $product->fresh()->sales_count);
    }

    public function test_editing_lines_moves_the_count(): void
    {
        $first = $this->stocked(20);
        $second = $this->stocked(20);

        $this->storeOrder([['product_id' => $first->id, 'quantity' => 3]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $line = $order->items()->firstOrFail();
        $this->assertSame(3, $first->fresh()->sales_count);

        $this->updateOrder($order, [['id' => $line->id, 'product_id' => $first->id, 'quantity' => 5]])->assertSessionHasNoErrors();
        $this->assertSame(5, $first->fresh()->sales_count);

        $this->updateOrder($order->fresh(), [['id' => $line->id, 'product_id' => $second->id, 'quantity' => 2]])->assertSessionHasNoErrors();
        $this->assertSame(0, $first->fresh()->sales_count);
        $this->assertSame(2, $second->fresh()->sales_count);

        $this->updateOrder($order->fresh(), [['id' => $line->id, 'product_id' => $second->id, 'quantity' => 2]], ['status' => OrderStatus::CANCELLED->value])->assertSessionHasNoErrors();
        $this->assertSame(0, $second->fresh()->sales_count);

        // Created already cancelled: nothing is counted.
        $this->storeOrder([['product_id' => $first->id, 'quantity' => 4]], ['status' => OrderStatus::CANCELLED->value])->assertSessionHasNoErrors();
        $this->assertSame(0, $first->fresh()->sales_count);
    }

    public function test_deleting_a_counted_order_takes_its_units_back(): void
    {
        $product = $this->stocked(20);
        $open = $this->checkout([[$product, 2]]);
        $delivered = $this->delivered($this->checkout([[$product, 3]]));
        $cancelled = $this->checkout([[$product, 4]]);
        $this->orders->changeStatus($cancelled, OrderStatus::CANCELLED, $this->staff->id);
        $this->assertSame(5, $product->fresh()->sales_count);

        $this->orders->delete($cancelled->fresh(), $this->staff->id);
        $this->assertSame(5, $product->fresh()->sales_count);

        // Several orders: one move once every order is deleted, after their stock, as a checkout does.
        Event::fake([ProductSalesChanged::class]);
        $this->orders->deleteMany([$open->id, $delivered->id], $this->staff->id);
        $this->assertSame(0, $product->fresh()->sales_count);
        Event::assertDispatchedTimes(ProductSalesChanged::class, 1);
    }

    public function test_wholesale_status_changes_move_nothing(): void
    {
        $product = $this->stocked(20);
        $order = $this->checkout([[$product, 4]], OrderChannel::B2B);

        $this->orders->changeStatus($order, OrderStatus::CANCELLED, $this->staff->id);
        $this->orders->delete($order->fresh(), $this->staff->id);

        $this->assertSame(0, $product->fresh()->sales_count);
    }

    public function test_recount_locks_the_orders_against_writes(): void
    {
        DB::enableQueryLog();
        app(SalesCounter::class)->recount();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertContains('lock table items_orders in share mode', array_map('strtolower', $queries));
    }

    public function test_the_migration_fills_the_counts_of_existing_orders(): void
    {
        $product = $this->stocked(20);
        $this->checkout([[$product, 3]]);
        DB::table($product->getTable())->update(['sales_count' => 0]);

        (require base_path('Modules/Items/database/migrations/2026_11_11_000004_fill_sales_counts.php'))->up();

        $this->assertSame(3, $product->fresh()->sales_count);
    }

    public function test_recount_matches_the_running_count(): void
    {
        [$shirt, $black] = $this->shirtWithVariant(50);
        $belt = $this->stocked(50);
        $other = $this->stocked(50);

        $this->checkout([[$black, 2], [$belt, 1]]);
        $this->checkout([[$belt, 4]], OrderChannel::B2B);
        $this->orders->changeStatus($this->checkout([[$black, 7]]), OrderStatus::CANCELLED, $this->staff->id);
        $this->orders->changeStatus($this->delivered($this->checkout([[$belt, 5]])), OrderStatus::RETURNED, $this->staff->id);
        $this->orders->delete($this->checkout([[$black, 9]]), $this->staff->id);

        $running = Product::query()->whereKey([$shirt->id, $belt->id, $other->id])->orderBy('id')->pluck('sales_count', 'id')->all();
        $this->assertSame([$shirt->id => 2, $belt->id => 1, $other->id => 0], $running);

        DB::table($shirt->getTable())->update(['sales_count' => 99]);

        $this->artisan('items:recount-sales')->assertSuccessful();

        $this->assertSame($running, Product::query()->whereKey([$shirt->id, $belt->id, $other->id])->orderBy('id')->pluck('sales_count', 'id')->all());
        $this->assertSame(0, $black->fresh()->sales_count);
    }

    public function test_the_best_selling_sort(): void
    {
        $few = $this->stocked(50);
        $many = $this->stocked(50);
        $none = $this->stocked(50);
        $this->checkout([[$few, 1]]);
        $this->checkout([[$many, 4]]);

        $this->assertSame([$many->id, $few->id, $none->id], $this->shop('best_selling'));
    }

    public function test_a_sale_refreshes_the_cached_best_selling_page(): void
    {
        // Stock not managed: the sale writes no product row, only the count.
        $first = $this->stocked(0, ['manage_stock' => false]);
        $second = $this->stocked(0, ['manage_stock' => false]);
        $this->checkout([[$first, 1]]);

        $this->assertSame([$first->id, $second->id], $this->shop('best_selling'));

        $this->checkout([[$second, 2]]);

        $this->assertSame([$second->id, $first->id], $this->shop('best_selling'));
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function checkout(array $lines, OrderChannel $channel = OrderChannel::B2C): Order
    {
        return $this->orders->checkout([
            'customer_name' => 'Buyer',
            'customer_email' => null,
            'contact_phone' => '0599123456',
            'delivery_address' => 'شارع 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'notes' => null,
            'payment_method' => PaymentMethod::CASH->value,
            'delivery_fee' => 0,
            'discount' => 0,
            'coupon_code' => null,
        ], array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1], 'price' => 100.0], $lines), $channel);
    }

    private function delivered(Order $order): Order
    {
        foreach ([OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED] as $status) {
            $order = $this->orders->changeStatus($order->fresh(), $status, $this->staff->id);
        }

        return $order->fresh();
    }

    /**
     * @return list<int>
     */
    private function shop(string $sort): array
    {
        $ids = [];

        $this->get($this->siteUrl('/ar/shop?sort='.$sort))->assertOk()->assertInertia(function ($page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['products']['data'], 'id');
            $page->etc();
        });

        return $ids;
    }
}
