<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Services\InventoryService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\DatabaseTestCase;

/**
 * A stock change typed in the product form is a movement in the ledger, and stock falling low tells
 * the staff who manage products.
 */
class ProductStockTest extends DatabaseTestCase
{
    private User $staff;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@stock.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');

        $this->category = Category::factory()->create();
    }

    public function test_changing_stock_in_the_form_writes_an_adjust_movement(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 10]);

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => 4, 'stock_quantity_loaded' => 10])->assertSessionHasNoErrors();

        $this->assertSame(4, $product->fresh()->stock_quantity);
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();
        $this->assertSame(StockMovementType::ADJUST, $movement->type);
        $this->assertSame(-6, $movement->quantity);
        $this->assertSame($this->staff->id, $movement->created_by);
    }

    public function test_saving_the_form_without_changing_stock_writes_nothing(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 10]);

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => 10, 'stock_quantity_loaded' => 10])->assertSessionHasNoErrors();

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_units_sold_while_the_form_was_open_stay_sold(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 1]);
        app(InventoryService::class)->sell([$product->id => 1]); // a buyer takes the last unit

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => 1, 'stock_quantity_loaded' => 1, 'description' => '<p>نص جديد</p>'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::ADJUST)->count());
    }

    public function test_a_change_typed_in_the_form_applies_as_a_difference(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 10]);
        app(InventoryService::class)->sell([$product->id => 3]); // 7 left while the form shows 10

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => 12, 'stock_quantity_loaded' => 10])->assertSessionHasNoErrors();

        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::query()->where('type', StockMovementType::ADJUST)->sole()->quantity);
    }

    public function test_a_change_that_no_longer_fits_the_stock_is_refused(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 5]);
        app(InventoryService::class)->sell([$product->id => 4]); // 1 left while the form shows 5

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => 2, 'stock_quantity_loaded' => 5])->assertSessionHasErrors('stock_quantity');

        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public function test_a_backorder_product_below_zero_can_still_be_saved(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'allow_backorder' => true, 'stock_quantity' => 1]);
        app(InventoryService::class)->sell([$product->id => 3]); // -2: owed to buyers

        $this->update($product, ['manage_stock' => true, 'allow_backorder' => true, 'stock_quantity' => -2, 'stock_quantity_loaded' => -2])
            ->assertSessionHasNoErrors();

        $this->assertSame(-2, $product->fresh()->stock_quantity);
    }

    public function test_a_typed_negative_stock_is_still_refused(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 3]);

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => -1, 'stock_quantity_loaded' => 3])->assertSessionHasErrors('stock_quantity');
    }

    public function test_a_save_without_the_loaded_value_leaves_stock_alone(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 10]);

        $this->update($product, ['manage_stock' => true, 'stock_quantity' => 3])->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_the_edit_page_sends_the_loaded_stock(): void
    {
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 6]);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/products/'.$product->id.'/edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.stock_quantity_loaded', 6));
    }

    public function test_a_new_product_records_its_opening_stock_as_incoming(): void
    {
        $slug = 'opening-'.Str::lower(Str::random(6));

        $this->store(['slug' => $slug, 'manage_stock' => true, 'stock_quantity' => 12])->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame(12, $product->stock_quantity);
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();
        $this->assertSame(StockMovementType::INCOMING, $movement->type);
        $this->assertSame(12, $movement->quantity);
    }

    public function test_an_unmanaged_product_keeps_the_typed_stock(): void
    {
        $product = Product::factory()->create(['manage_stock' => false, 'stock_quantity' => 0]);

        $this->update($product, ['manage_stock' => false, 'stock_quantity' => 7, 'stock_quantity_loaded' => 0])->assertSessionHasNoErrors();

        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_a_variation_stock_change_is_recorded(): void
    {
        $slug = 'variant-stock-'.Str::lower(Str::random(6));
        $this->store(['slug' => $slug, 'variations' => [$this->row(['manage_stock' => true, 'stock_quantity' => 5])]])->assertSessionHasNoErrors();

        $variation = Product::query()->where('slug', $slug)->firstOrFail()->variations()->firstOrFail();
        $this->assertSame(5, $variation->stock_quantity);
        $this->assertSame(StockMovementType::INCOMING, StockMovement::query()->where('product_id', $variation->id)->sole()->type);
    }

    public function test_staff_who_manage_products_get_a_low_stock_notification(): void
    {
        Notification::fake();
        $customer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer-'.Str::random(8).'@stock.test', 'password' => 'secret-password'])
            ->assignRole('Customer');
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 5, 'low_stock_threshold' => 3]);

        app(InventoryService::class)->sell([$product->id => 3]);
        // The alert goes once the response is out (outside a request: when the deferred callbacks run).
        app(DeferredCallbackCollection::class)->invoke();

        Notification::assertSentTo($this->staff, GeneralNotification::class);
        Notification::assertNotSentTo($customer, GeneralNotification::class);
    }

    public function test_a_failing_low_stock_alert_never_fails_the_sale(): void
    {
        // Without the permission spatie throws when looking the recipients up, after the commit.
        Permission::query()->where('name', 'Product Update')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $product = Product::factory()->create(['manage_stock' => true, 'stock_quantity' => 5, 'low_stock_threshold' => 3]);

        app(InventoryService::class)->sell([$product->id => 3]);
        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    private function row(array $overrides = []): array
    {
        return [
            'name' => 'متغير '.Str::random(4),
            'base_price' => 100,
            'category_id' => [$this->category->id],
            'is_featured' => false,
            'is_digital' => false,
            ...$overrides,
        ];
    }

    private function fields(array $overrides = []): array
    {
        return [
            'name' => 'منتج',
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::PUBLISHED->value,
            'base_price' => 100,
            'category_id' => [$this->category->id],
            'description' => '<p>وصف</p>',
            'is_featured' => false,
            'is_digital' => false,
            ...$overrides,
        ];
    }

    private function store(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/products'), [
            'payload' => json_encode($this->fields($overrides)),
            'image' => UploadedFile::fake()->image('product.jpg'),
        ]);
    }

    private function update(Product $product, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/products/'.$product->id), $this->fields([
            'slug' => $product->slug,
            'base_price' => $product->base_price,
            'status' => $product->status->value,
            ...$overrides,
        ]));
    }
}
