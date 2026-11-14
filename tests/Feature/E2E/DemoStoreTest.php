<?php

namespace Tests\Feature\E2E;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Services\ProductService;
use Modules\Store\Services\Demo\DemoStore;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * `php artisan malbo:demo`: a real store's worth of data, written through the services, opt-in,
 * never in production, and a second run changes nothing.
 */
class DemoStoreTest extends E2ETestCase
{
    public function test_the_demo_store_is_a_coherent_store(): void
    {
        $demo = $this->demo();

        $this->assertCount(15, $demo->products);
        $this->assertCount(2, $demo->bundles);
        $this->assertGreaterThan(100, count($demo->items));

        foreach ($demo->products as $key => $product) {
            foreach (['ar', 'en', 'he'] as $locale) {
                $this->assertNotEmpty($product->fresh()->translate($locale, false)?->name, "{$key} has a {$locale} name");
            }
        }

        $this->assertTrue($demo->sizes['M']->attribute->is_size);
        $this->assertSame(OrderStatus::DELIVERED, $demo->orders['delivered-old']->fresh()->status);
        $this->assertSame(OrderStatus::CANCELLED, $demo->orders['cancelled']->fresh()->status);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $demo->orders['awaiting']->fresh()->status);
        $this->assertSame(MerchantStatus::APPROVED, $demo->extra['merchants']['approved']->status);
        $this->assertSame(MerchantStatus::PENDING, $demo->extra['merchants']['pending']->status);
        $this->assertSame(1, LedgerEntry::query()->where('order_id', $demo->extra['wholesale_orders']['delivered']->id)->where('type', 'invoice')->count());

        // Every unit sold or given back is in the ledger: stock = opening + movements.
        foreach (Product::query()->whereNull('bundle_type')->where('manage_stock', true)->get() as $item) {
            $this->assertSame((int) $item->stock_quantity, (int) StockMovement::query()->where('product_id', $item->id)->sum('quantity'), "{$item->sku} matches its ledger");
        }

        $this->assertSame(['Customer (B2C)', 'Customer (B2C)', 'Merchant (approved, gold tier)', 'Merchant (pending approval)'], array_column($demo->logins, 0));
    }

    /**
     * A product saved with no stock (the scarf, opening stock 0) is sold out, not "in stock" with an
     * empty number, and staff see it on the low stock page.
     */
    public function test_an_item_opened_with_no_stock_is_out_of_stock(): void
    {
        $scarf = $this->demo()->products['wool-scarf']->fresh();

        $this->assertSame(0, $scarf->stock_quantity);
        $this->assertSame('out_of_stock', $scarf->stock_status);

        $created = app(ProductService::class)->create(['name' => 'جوارب', 'slug' => 'socks-e2e', 'base_price' => 20, 'manage_stock' => true, 'stock_quantity' => 0], 'ar', $this->admin()->id);
        $this->assertSame([0, 'out_of_stock'], [$created->fresh()->stock_quantity, $created->fresh()->stock_status]);

        $this->actingAs($this->admin())->get($this->siteUrl('/ar/admin/stock/low'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.data', fn ($rows) => collect($rows)->pluck('id')->contains($scarf->id))->etc());
    }

    public function test_the_command_runs_once_and_touches_nothing_else(): void
    {
        $category = Category::query()->create(['slug' => 'shirts', 'status' => 1, 'sort_order' => 0]);
        $category->translateOrNew('ar')->name = 'قمصان المتجر';
        $category->save();
        $theirs = Product::factory()->create(['slug' => 'oxford-shirt', 'sku' => 'THEIRS-1', 'manage_stock' => true, 'stock_quantity' => 7]);
        $users = User::query()->count();

        $this->artisan('malbo:demo', ['--no-images' => true])->assertSuccessful();

        $this->assertSame('قمصان المتجر', $category->fresh()->name);
        $this->assertSame([7, 'oxford-shirt', 'THEIRS-1'], [$theirs->fresh()->stock_quantity, $theirs->fresh()->slug, $theirs->fresh()->sku]);
        $this->assertSame('oxford-shirt-2', Product::query()->where('sku', 'MLB-OXFSHI')->value('slug'), 'the demo takes the next free slug');
        $this->assertSame(0, StockMovement::query()->where('product_id', $theirs->id)->count());

        $counts = [User::query()->count(), Product::query()->count(), Order::query()->count(), StockMovement::query()->count()];
        $this->assertGreaterThan($users, $counts[0]);

        $this->artisan('malbo:demo')->expectsOutputToContain('already here')->assertSuccessful();
        $this->assertSame($counts, [User::query()->count(), Product::query()->count(), Order::query()->count(), StockMovement::query()->count()]);
        $this->assertNotNull(User::query()->where('email', DemoStore::BOT_EMAIL)->first());
    }

    public function test_the_command_refuses_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('malbo:demo')->expectsOutputToContain('never built in production')->assertFailed();
        $this->assertNull(User::query()->where('email', DemoStore::BOT_EMAIL)->first());
    }

    public function test_the_opening_stock_is_an_incoming_movement(): void
    {
        $item = $this->demo()->item('polo', 'navy', 'M');

        $this->assertSame(StockMovementType::INCOMING, StockMovement::query()->where('product_id', $item->id)->oldest('id')->first()->type);
    }
}
