<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\ProductStatus;
use Tests\TenantTestCase;

/**
 * The browser or app says what and how many. Every amount of an order comes from the database.
 */
class OrderApiTest extends TenantTestCase
{
    private User $buyer;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer-'.Str::lower(Str::random(8)).'@orders.test', 'password' => 'password']);
        $this->branch = Branch::factory()->create();
    }

    public function test_the_total_is_computed_on_the_server(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);

        $response = $this->place([['product_id' => $product->id, 'quantity' => 2, 'price' => 0.01, 'total_price' => 0.01]], [
            'total' => 0.01,
            'sub_total' => 0.01,
            'discount' => 999,
            'delivery_fee' => -50,
            'status' => 6,
            'payment_status' => 2,
            'user_id' => 1,
        ])->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));

        $this->assertEquals(200, $order->sub_total);
        $this->assertEquals(200, $order->total);
        $this->assertEquals(0, $order->delivery_fee);
        $this->assertSame($this->buyer->id, $order->user_id);
        $this->assertEquals(100, $order->items()->first()->price);
    }

    public function test_a_sale_price_is_used_and_a_zero_sale_is_no_sale(): void
    {
        $this->assertTotal(80, ['base_price' => 100, 'sale_price' => 80]);
        $this->assertTotal(100, ['base_price' => 100, 'sale_price' => 0]);
    }

    public function test_a_percentage_offer_lowers_the_price(): void
    {
        $product = Product::factory()->create(['base_price' => 200]);
        $this->offer($product, OfferType::PERCENTAGE, 25);

        $this->assertEquals(150, $this->totalOf($product));
    }

    public function test_a_fixed_offer_lowers_the_price_and_never_below_zero(): void
    {
        $product = Product::factory()->create(['base_price' => 200]);
        $this->offer($product, OfferType::FIXED, 30);
        $this->assertEquals(170, $this->totalOf($product));

        $cheap = Product::factory()->create(['base_price' => 10]);
        $this->offer($cheap, OfferType::FIXED, 30);
        $this->assertEquals(0, $this->totalOf($cheap));
    }

    public function test_a_free_shipping_offer_does_not_change_the_price(): void
    {
        $product = Product::factory()->create(['base_price' => 200]);
        $this->offer($product, OfferType::FREE_SHIPPING, 50);

        $this->assertEquals(200, $this->totalOf($product));
    }

    public function test_an_offer_of_another_branch_or_out_of_date_does_not_apply(): void
    {
        $product = Product::factory()->create(['base_price' => 200]);
        $this->offer($product, OfferType::PERCENTAGE, 50, ['branch_id' => Branch::factory()->create()->id]);
        $this->offer($product, OfferType::PERCENTAGE, 50, ['end_date' => now()->subDay()]);
        $this->offer($product, OfferType::PERCENTAGE, 50, ['is_active' => false]);

        $this->assertEquals(200, $this->totalOf($product));
    }

    public function test_an_offer_of_this_branch_applies(): void
    {
        $product = Product::factory()->create(['base_price' => 200]);
        $this->offer($product, OfferType::PERCENTAGE, 50, ['branch_id' => $this->branch->id]);

        $this->assertEquals(100, $this->totalOf($product));
    }

    public function test_addons_are_priced_from_the_database_with_the_custom_price_of_the_product(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);
        $addon = Addon::factory()->create();
        $plain = AddonOption::factory()->for($addon)->create(['price' => 5]);
        $custom = AddonOption::factory()->for($addon)->create(['price' => 5]);
        $this->link($product, $addon, [$custom->id => 7]);

        $response = $this->place([[
            'product_id' => $product->id,
            'quantity' => 1,
            'addons' => [
                ['addon_option_id' => $plain->id, 'quantity' => 2, 'price' => 0],
                ['addon_option_id' => $custom->id, 'quantity' => 1, 'price' => 0],
            ],
        ]])->assertCreated();

        $this->assertEquals(100 + 10 + 7, Order::findOrFail($response->json('data.id'))->total);
    }

    public function test_an_option_the_product_does_not_offer_is_refused(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);
        $foreign = AddonOption::factory()->for(Addon::factory()->create())->create(['price' => 1]);

        $this->place([['product_id' => $product->id, 'quantity' => 1, 'addons' => [['addon_option_id' => $foreign->id, 'quantity' => 1]]]])
            ->assertStatus(422);

        $this->assertSame(0, Order::where('user_id', $this->buyer->id)->count());
    }

    public function test_a_product_that_is_not_for_sale_is_refused(): void
    {
        foreach ([['status' => ProductStatus::DRAFT], ['is_active' => 0]] as $state) {
            $product = Product::factory()->create(['base_price' => 100, ...$state]);

            $this->place([['product_id' => $product->id, 'quantity' => 1]])->assertStatus(422);
        }

        $this->assertSame(0, Order::where('user_id', $this->buyer->id)->count());
    }

    public function test_a_variation_of_a_draft_product_is_refused(): void
    {
        $parent = Product::factory()->create(['status' => ProductStatus::DRAFT]);
        $variation = Product::factory()->variationOf($parent)->create(['status' => ProductStatus::PUBLISHED]);

        $this->place([['product_id' => $variation->id, 'quantity' => 1]])->assertStatus(422);
    }

    public function test_bad_references_and_quantities_are_a_422(): void
    {
        $product = Product::factory()->create();

        $this->place([['product_id' => 99999999, 'quantity' => 1]])->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->place([['product_id' => $product->id, 'quantity' => 0]])->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->place([['product_id' => $product->id, 'quantity' => 1]], ['branch_id' => 99999999])->assertStatus(422);
    }

    public function test_the_buyer_is_notified_in_the_database(): void
    {
        $product = Product::factory()->create();

        $this->place([['product_id' => $product->id, 'quantity' => 1]])->assertCreated();

        $this->assertSame(1, $this->buyer->unreadNotifications()->count());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertTotal(float $expected, array $state): void
    {
        $this->assertEquals($expected, $this->totalOf(Product::factory()->create($state)));
    }

    private function totalOf(Product $product): float
    {
        $this->app['auth']->forgetGuards();

        $id = $this->place([['product_id' => $product->id, 'quantity' => 1]])->assertCreated()->json('data.id');

        return (float) Order::findOrFail($id)->total;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     */
    private function place(array $items, array $extra = []): TestResponse
    {
        return $this->postJson($this->tenantUrl('/api/v1/items/orders'), [
            'branch_id' => $this->branch->id,
            'payment_method' => 1,
            'delivery_type' => 1,
            'contact_phone' => '0599000000',
            'items' => $items,
            ...$extra,
        ], ['Authorization' => 'Bearer '.$this->buyer->createToken('test')->plainTextToken]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function offer(Product $product, OfferType $type, float $value, array $attributes = []): Offer
    {
        return Offer::create([
            'type' => $type,
            'value' => $value,
            'is_active' => true,
            'offerable_type' => $product->getMorphClass(),
            'offerable_id' => $product->id,
            'created_by' => $this->buyer->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<int, float>  $customPrices  option id => price
     */
    private function link(Product $product, Addon $addon, array $customPrices): void
    {
        $prefix = config('items.table_prefix');

        $pivot = DB::table($prefix.'addon_product')->insertGetId([
            'addon_id' => $addon->id, 'product_id' => $product->id, 'is_required' => 0, 'min_selection' => 0,
            'max_selection' => 5, 'max_quantity_per_option' => 5, 'created_by' => $this->buyer->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($customPrices as $option => $price) {
            DB::table($prefix.'addon_product_option')->insert([
                'addon_product_id' => $pivot, 'addon_option_id' => $option, 'price' => $price, 'is_default' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
