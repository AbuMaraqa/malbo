<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Services\InventoryService;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Mail\BackInStockMail;
use Modules\Store\Repositories\Contracts\ReviewRepositoryInterface;
use Modules\Store\Services\StockAlertService;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Items\Concerns\MakesBundles;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Selling a set: one order line per piece (the variant the buyer chose), the set's price shared out
 * over them, the pieces' stock taken in one locked step, and given back on a cancellation or return.
 */
class BundleCheckoutTest extends StorefrontTestCase
{
    use MakesBundles;
    use MakesShippingZones;

    private Product $set;

    /** @var array<string, Product> */
    private array $shirts;

    /** @var array<string, Product> */
    private array $trousers;

    private City $city;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        [$shirt, $this->shirts] = $this->sized('قميص', ['M' => 3, 'L' => 2], 120);
        [$trousers, $this->trousers] = $this->sized('بنطال', ['32' => 1, '34' => 5], 180);
        $this->set = $this->bundle([[$shirt, 1], [$trousers, 1]], ['name' => 'طقم الصيف', 'slug' => 'summer-set', 'sku' => 'SET-SUMMER', 'base_price' => 240]);

        $this->city = $this->city('رام الله');
        $this->zone([$this->city], ['fee' => 20]);
        $this->token = $this->addSet('M', '32');
    }

    public function test_a_bundle_order_writes_component_lines_and_stock(): void
    {
        $this->place()->assertSessionHasNoErrors()->assertRedirect(route('store.checkout.done'));

        $order = Order::query()->with('items')->sole();
        $this->assertSame(['240.00', '20.00', '260.00'], [$order->sub_total, $order->delivery_fee, $order->total]);

        [$shirt, $trousers] = $order->items->sortBy('id')->values()->all();
        $this->assertSame([$this->shirts['M']->id, 1, '96.00', '96.00'], [$shirt->product_id, $shirt->quantity, $shirt->price, $shirt->total_price]);
        $this->assertSame([$this->trousers['32']->id, 1, '144.00', '144.00'], [$trousers->product_id, $trousers->quantity, $trousers->price, $trousers->total_price]);
        $this->assertSame($this->set->id, $shirt->bundle_product_id);
        $this->assertSame($shirt->bundle_group, $trousers->bundle_group);
        $this->assertTrue(Str::isUuid($shirt->bundle_group));
        $this->assertSame('SET-SUMMER', $shirt->bundle['sku']);
        $this->assertSame('طقم الصيف', $shirt->bundle['name']['ar']);
        $this->assertSame([1, 240.0, 'set'], [$shirt->bundle['quantity'], (float) $shirt->bundle['unit_price'], $shirt->bundle['type']]);
        $this->assertSame('قميص', $shirt->product_name['ar']);
        $this->assertSame('M', $shirt->options[0]['value']['ar']);

        $this->assertSame(2, $this->shirts['M']->fresh()->stock_quantity);
        $this->assertSame(0, $this->trousers['32']->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, (int) $this->set->fresh()->stock_quantity, 'the set itself holds no stock');
    }

    public function test_two_sets_on_a_line_take_twice_the_pieces(): void
    {
        $this->trousers['32']->update(['stock_quantity' => 5]);
        Cart::query()->sole()->items()->update(['quantity' => 2]);

        $this->place()->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->sole();
        $this->assertSame([2, 2], $order->items->pluck('quantity')->all());
        $this->assertSame('480.00', $order->sub_total);
        $this->assertSame(1, $this->shirts['M']->fresh()->stock_quantity);
    }

    public function test_a_bundle_and_its_component_alone_cannot_oversell(): void
    {
        // Each line fits the stock on its own; together they need two trousers of size 32.
        $this->withCookie(CartCookie::NAME, $this->token)->post($this->siteUrl('/ar/cart'), ['product_id' => $this->trousers['32']->id, 'quantity' => 1])->assertSessionHasNoErrors();

        $this->place()->assertSessionHasErrors('items');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $this->trousers['32']->fresh()->stock_quantity);
        $this->assertSame(3, $this->shirts['M']->fresh()->stock_quantity);
        $this->assertNull(Cart::query()->sole()->order_id);
    }

    public function test_cancel_puts_the_components_back(): void
    {
        $this->place()->assertSessionHasNoErrors();
        $order = Order::query()->sole();

        app(OrderService::class)->changeStatus($order, OrderStatus::CANCELLED, $this->staffId());

        $this->assertSame(3, $this->shirts['M']->fresh()->stock_quantity);
        $this->assertSame(1, $this->trousers['32']->fresh()->stock_quantity);
    }

    public function test_a_partial_return_refunds_the_allocated_price(): void
    {
        $this->place()->assertSessionHasNoErrors();
        $order = Order::query()->with('items')->sole();
        $order->forceFill(['status' => OrderStatus::DELIVERED])->save();
        $shirt = $order->items->firstWhere('product_id', $this->shirts['M']->id);

        $request = app(ReturnRequestService::class)->create($order, ['type' => 'return', 'reason' => 'wrong_size', 'lines' => [['order_item_id' => $shirt->id, 'quantity' => 1]]], staffId: $this->staffId());

        $this->assertSame('96.00', $request->refund_amount);
    }

    public function test_a_bundle_row_is_never_an_order_line(): void
    {
        $orders = app(OrderService::class);

        foreach ([
            fn () => $orders->create(['status' => OrderStatus::PENDING->value, 'payment_method' => PaymentMethod::CASH->value, 'delivery_type' => DeliveryType::DELIVERY->value, 'items' => [['product_id' => $this->set->id, 'quantity' => 1]]], $this->staffId()),
            fn () => $orders->place(['order_number' => 'API-1', 'payment_method' => PaymentMethod::CASH->value, 'sub_total' => 240, 'total' => 240], [['product_id' => $this->set->id, 'quantity' => 1, 'price' => 240, 'total_price' => 240, 'notes' => null, 'options_to_attach' => []]], $this->staffId()),
            fn () => $orders->checkout(['payment_method' => PaymentMethod::CASH->value, 'customer_name' => 'X', 'contact_phone' => '0599123456'], [['product_id' => $this->set->id, 'quantity' => 1, 'price' => 240]]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A set is sold through its components only.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('items', $e->errors());
            }
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_admin_edit_keeps_or_removes_a_set_whole(): void
    {
        $belt = Product::factory()->create(['name' => 'حزام', 'base_price' => 50, 'manage_stock' => false]);
        $this->withCookie(CartCookie::NAME, $this->token)->post($this->siteUrl('/ar/cart'), ['product_id' => $belt->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->place()->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->sole();
        $orders = app(OrderService::class);
        $rows = fn (?callable $map = null) => $order->items->sortBy('id')->map(fn (OrderItem $item) => ($map ?? fn ($row) => $row)(['id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $item->quantity]))->values()->all();
        $data = fn (array $items) => ['status' => OrderStatus::AWAITING_CONFIRMATION->value, 'delivery_type' => DeliveryType::DELIVERY->value, 'delivery_fee' => 20, 'payment_method' => PaymentMethod::CASH->value, 'items' => $items];

        $orders->update($order->id, $data($rows()), $this->staffId());
        $this->assertSame('290.00', $order->fresh()->sub_total, 'kept as sold');

        foreach ([
            array_map(fn (array $row) => $row['product_id'] === $this->shirts['M']->id ? [...$row, 'quantity' => 2] : $row, $rows()),
            array_values(array_filter($rows(), fn (array $row) => $row['product_id'] !== $this->shirts['M']->id)),
        ] as $items) {
            try {
                $orders->update($order->id, $data($items), $this->staffId());
                $this->fail('A set changes only as a whole.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('items', $e->errors());
            }
        }

        $orders->update($order->id, $data(array_values(array_filter($rows(), fn (array $row) => $row['product_id'] === $belt->id))), $this->staffId());
        $this->assertSame('50.00', $order->fresh()->sub_total);
        $this->assertSame(3, $this->shirts['M']->fresh()->stock_quantity, 'the removed set gives its pieces back');
        $this->assertSame(1, $this->trousers['32']->fresh()->stock_quantity);
    }

    public function test_back_in_stock_reaches_bundle_subscribers(): void
    {
        Mail::fake();
        foreach ($this->shirts as $shirt) {
            $shirt->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        }
        StockAlert::query()->create(['product_id' => $this->set->id, 'email' => 'buyer@example.com', 'locale' => 'ar']);

        app(InventoryService::class)->receive($this->shirts['L'], 2, null, $this->staffId());

        Mail::assertSent(BackInStockMail::class, fn (BackInStockMail $mail) => $mail->hasTo('buyer@example.com') && $mail->url === route('store.product', ['slug' => 'summer-set']));
        $this->assertNotNull(StockAlert::query()->sole()->notified_at);
        $this->assertSame(0, app(StockAlertService::class)->notifyAvailable([$this->shirts['L']->id]), 'once');
    }

    public function test_a_set_buyer_may_review_it(): void
    {
        $this->place()->assertSessionHasNoErrors();
        $buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'b-'.Str::random(6).'@store.test', 'password' => 'secret-password']);
        Order::query()->update(['user_id' => $buyer->id, 'status' => OrderStatus::DELIVERED]);

        $this->assertNotNull(app(ReviewRepositoryInterface::class)->eligibleOrderId($buyer->id, $this->set->id));
    }

    private function addSet(string $shirt, string $trousers, ?string $token = null): string
    {
        [$first, $second] = $this->set->bundleComponents()->get()->all();
        $request = $token ? $this->withCookie(CartCookie::NAME, $token) : $this;
        $response = $request->post($this->siteUrl('/ar/cart'), [
            'product_id' => $this->set->id,
            'quantity' => 1,
            'components' => [$first->id => $this->shirts[$shirt]->id, $second->id => $this->trousers[$trousers]->id],
        ])->assertSessionHasNoErrors();

        return $token ?? (string) $response->getCookie(CartCookie::NAME)?->getValue();
    }

    private function place(): TestResponse
    {
        return $this->withCookie(CartCookie::NAME, $this->token)
            ->from($this->siteUrl('/ar/checkout'))
            ->post($this->siteUrl('/ar/checkout'), [
                'submit_token' => (string) Str::uuid(),
                'name' => 'Guest Buyer',
                'phone' => '0599123456',
                'email' => 'buyer@example.com',
                'city_id' => $this->city->id,
                'district_id' => null,
                'address' => 'شارع الإرسال 5',
                'payment_method' => 'cash',
            ]);
    }

    private function staffId(): int
    {
        return (int) $this->staff(['Order Update'])->id;
    }
}
