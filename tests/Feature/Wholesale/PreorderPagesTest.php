<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Services\OrderService;
use Modules\Wholesale\Entities\Merchant;

/**
 * Incoming goods in the admin (on a product's wholesale prices page) and the pre-orders list: B2B
 * order lines that reserved incoming goods, grouped by product, with the expected date.
 */
class PreorderPagesTest extends WholesaleTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_the_edit_page_shows_incoming_per_item(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['black-S']->forceFill(['incoming_quantity' => 4, 'incoming_expected_at' => self::day(20)])->save();

        $this->actingAs($this->admin)->get($this->url('/prices/'.$shirt->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/ProductPrice/Edit')
            ->where('items.0.incoming_quantity', 4)
            ->where('items.0.incoming_expected_at', self::day(20))
            ->where('items.0.stock_quantity', 10)
            ->where('items.1.incoming_quantity', 0)
            ->where('items.1.incoming_expected_at', null)
            ->etc());
    }

    public function test_the_incoming_card_saves_quantities_and_dates(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['black-M']->forceFill(['incoming_quantity' => 3, 'incoming_expected_at' => self::day(20)])->save();

        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $variants['black-S']->id => ['quantity' => 6, 'expected_at' => self::day(34), 'loaded_quantity' => 0, 'loaded_expected_at' => null],
            $variants['black-M']->id => ['quantity' => 0, 'expected_at' => null, 'loaded_quantity' => 3, 'loaded_expected_at' => self::day(20)],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(6, (int) $variants['black-S']->fresh()->incoming_quantity);
        $this->assertSame(self::day(34), $variants['black-S']->fresh()->incoming_expected_at->toDateString());
        $this->assertSame(0, (int) $variants['black-M']->fresh()->incoming_quantity);
        $this->assertNull($variants['black-M']->fresh()->incoming_expected_at);
        $this->assertSame(10, (int) $variants['black-S']->fresh()->stock_quantity);
    }

    public function test_a_date_is_needed_with_a_quantity(): void
    {
        [$shirt, $variants] = $this->shirt();

        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $variants['black-S']->id => ['quantity' => 5, 'expected_at' => null, 'loaded_quantity' => 0, 'loaded_expected_at' => null],
        ]])->assertSessionHasErrors('incoming.'.$variants['black-S']->id.'.expected_at');
    }

    public function test_an_expected_date_in_the_past_is_refused(): void
    {
        [$shirt, $variants] = $this->shirt();

        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $variants['black-S']->id => ['quantity' => 5, 'expected_at' => now()->subDay()->toDateString(), 'loaded_quantity' => 0, 'loaded_expected_at' => null],
        ]])->assertSessionHasErrors('incoming.'.$variants['black-S']->id.'.expected_at');

        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $variants['black-S']->id => ['quantity' => 5, 'expected_at' => now()->toDateString(), 'loaded_quantity' => 0, 'loaded_expected_at' => null],
        ]])->assertSessionHasNoErrors();
    }

    public function test_saving_another_row_leaves_goods_received_meanwhile_alone(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['black-S']->forceFill(['incoming_quantity' => 100, 'incoming_expected_at' => self::day(20)])->save();

        // The page is opened (black S: 100 on their way), then 30 arrive.
        $this->actingAs($this->admin)->get($this->url('/prices/'.$shirt->id.'/edit'))->assertOk();
        app(\Modules\Items\Services\InventoryService::class)->receive($variants['black-S']->fresh(), 30, null, null);

        // Saving black M sends black M only: black S keeps the 70 still on their way.
        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $variants['black-M']->id => ['quantity' => 8, 'expected_at' => self::day(29), 'loaded_quantity' => 0, 'loaded_expected_at' => null],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(70, (int) $variants['black-S']->fresh()->incoming_quantity);
        $this->assertSame(8, (int) $variants['black-M']->fresh()->incoming_quantity);
    }

    public function test_a_row_changed_since_the_page_was_opened_is_refused(): void
    {
        [$shirt, $variants] = $this->shirt();
        $variants['black-S']->forceFill(['incoming_quantity' => 100, 'incoming_expected_at' => self::day(20)])->save();
        app(\Modules\Items\Services\InventoryService::class)->receive($variants['black-S']->fresh(), 30, null, null);

        // Typed over the 100 the page showed: the 70 left must not become 120 unseen.
        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $variants['black-S']->id => ['quantity' => 120, 'expected_at' => self::day(20), 'loaded_quantity' => 100, 'loaded_expected_at' => self::day(20)],
        ]])->assertSessionHasErrors('incoming.'.$variants['black-S']->id.'.quantity');

        $this->assertSame(70, (int) $variants['black-S']->fresh()->incoming_quantity);
        $this->assertStringContainsString('70', session('errors')->first('incoming.'.$variants['black-S']->id.'.quantity'));
    }

    public function test_incoming_of_another_product_is_refused(): void
    {
        [$shirt] = $this->shirt();
        $belt = $this->single();

        $this->actingAs($this->admin)->from($this->url('/prices/'.$shirt->id.'/edit'))->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [
            $belt->id => ['quantity' => 5, 'expected_at' => self::day(20), 'loaded_quantity' => 0, 'loaded_expected_at' => null],
        ]])->assertSessionHasErrors('incoming');

        $this->assertSame(0, (int) $belt->fresh()->incoming_quantity);
    }

    public function test_the_preorders_list_groups_lines_by_product(): void
    {
        $belt = $this->single(['stock_quantity' => 0, 'stock_status' => 'out_of_stock', 'sku' => 'BELT-1']);
        $belt->forceFill(['incoming_quantity' => 10, 'incoming_expected_at' => self::day(20)])->save();
        [, $variants] = $this->shirt();
        $variants['black-S']->forceFill(['stock_quantity' => 1, 'incoming_quantity' => 5, 'incoming_expected_at' => self::day(39)])->save();

        $first = $this->b2b($this->merchant(), [[$belt, 3], [$variants['black-S'], 2]]);
        $second = $this->b2b($this->merchant(attributes: ['shop_name' => 'محل ثان']), [[$belt, 2]]);
        $this->b2b($this->merchant(), [[$variants['black-M'], 2]]); // from stock: not a pre-order

        $this->actingAs($this->admin)->get($this->url('/preorders'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Preorder/Index')
            ->where('table.meta.total', 2)
            ->where('table.data.0.id', $belt->id)
            ->where('table.data.0.sku', 'BELT-1')
            ->where('table.data.0.preordered', 5)
            ->where('table.data.0.incoming_quantity', 10)
            ->where('table.data.0.expected_at', self::day(20))
            ->where('table.data.0.stock', -5)
            ->where('table.data.0.orders', fn ($orders) => collect($orders)->pluck('number')->sort()->values()->all() === collect([$first->order_number, $second->order_number])->sort()->values()->all())
            ->where('table.data.1.id', $variants['black-S']->id)
            ->where('table.data.1.preordered', 1)
            ->where('table.data.1.name', 'قميص جملة')
            ->etc());
    }

    public function test_closed_orders_are_left_out(): void
    {
        $belt = $this->single(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $belt->forceFill(['incoming_quantity' => 10, 'incoming_expected_at' => self::day(20)])->save();
        $order = $this->b2b($this->merchant(), [[$belt, 3]]);

        app(OrderService::class)->changeStatus($order, OrderStatus::CANCELLED, $this->admin->id);

        $this->actingAs($this->admin)->get($this->url('/preorders'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0)->etc());
    }

    public function test_each_filter(): void
    {
        $belt = $this->single(['stock_quantity' => 0, 'stock_status' => 'out_of_stock', 'sku' => 'BELT-9']);
        $belt->forceFill(['incoming_quantity' => 10, 'incoming_expected_at' => self::day(20)])->save();
        $cap = $this->single(['name' => 'قبعة', 'stock_quantity' => 0, 'stock_status' => 'out_of_stock', 'sku' => 'CAP-9']);
        $cap->forceFill(['incoming_quantity' => 2, 'incoming_expected_at' => self::day(60)])->save();
        $this->b2b($this->merchant(), [[$belt, 3], [$cap, 2]]);

        $only = fn (string $query, int $id) => $this->actingAs($this->admin)->get($this->url('/preorders?'.$query))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $id)->etc());

        $only('filter[search]=CAP', $cap->id);
        $only('filter[search]=حزام', $belt->id);
        $only('filter[expected_at_from]='.self::day(51), $cap->id);
        $only('filter[expected_at_to]='.self::day(50), $belt->id);
        // Received: the caps arrived (none on their way); the order still waits for them.
        app(\Modules\Items\Services\InventoryService::class)->receive($cap, 2, null, null);
        $only('filter[received]=1', $cap->id);
        $only('filter[received]=0', $belt->id);
    }

    public function test_every_action_needs_its_permission(): void
    {
        [$shirt, $variants] = $this->shirt();
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Wholesale Price View List');

        $this->actingAs($viewer)->get($this->url('/preorders'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/prices/'.$shirt->id.'/incoming'), ['incoming' => [$variants['black-S']->id => ['quantity' => 5, 'expected_at' => self::day(20)]]])->assertForbidden();

        $viewer->givePermissionTo('Wholesale Preorder View List');
        $this->actingAs($viewer)->get($this->url('/preorders'))->assertOk();
        $this->assertSame(0, (int) $variants['black-S']->fresh()->incoming_quantity);
    }

    /**
     * A B2B order placed through OrderService, as the wholesale checkout places it.
     *
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function b2b(Merchant $merchant, array $lines): Order
    {
        return app(OrderService::class)->checkout([
            'user_id' => $merchant->user_id, 'customer_name' => $merchant->shop_name, 'contact_phone' => $merchant->phone,
            'delivery_address' => 'x', 'payment_method' => 1, 'delivery_fee' => 0, 'discount' => 0,
        ], array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1], 'price' => 20], $lines), OrderChannel::B2B);
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin/wholesale'.$path);
    }
}
