<?php

namespace Tests\Feature\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;

class OrderPagesTest extends DatabaseTestCase
{
    private User $staff;

    private User $customer;

    private Branch $branch;

    private City $city;

    private District $district;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');

        $this->customer = User::forceCreate([
            'name' => 'Ahmad Customer',
            'email' => 'customer-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ]);

        $this->branch = Branch::factory()->create();
        [$this->city, $this->district] = $this->cityWithDistrict();
        $this->product = Product::factory()->create(['base_price' => 100, 'sale_price' => null]);
    }

    // ---------------------------------------------------------------- the list

    public function test_the_list_is_shaped_for_the_table(): void
    {
        $order = $this->order(['total' => 150]);
        $order->items()->create(['product_id' => $this->product->id, 'price' => 75, 'quantity' => 2, 'total_price' => 150]);

        $this->page('/orders')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Order/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $order->id)
            ->where('table.data.0.customer', 'Ahmad Customer')
            ->where('table.data.0.status', OrderStatus::PENDING->getLabel())
            ->where('table.data.0.status_tone', 'warning')
            ->where('table.data.0.items.0.quantity', 2)
            ->has('statuses', 9)
            ->missing('table.data.0.created_by'));
    }

    public function test_search_in_another_letter_case(): void
    {
        $this->order(['order_number' => 'ABC-1']);
        $other = User::forceCreate(['name' => 'Layla', 'email' => 'l-'.Str::random(8).'@items.test', 'password' => 'secret-password']);
        $this->order(['order_number' => 'XYZ-2', 'user_id' => $other->id]);

        $this->page('/orders?filter[search]=abc')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.order_number', 'ABC-1'));

        $this->page('/orders?filter[search]=LAYLA')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.order_number', 'XYZ-2'));

        $this->page('/orders?filter[customer]=aHMAD')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.order_number', 'ABC-1'));

        $this->page('/orders?filter[order_number]=xyz')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.order_number', 'XYZ-2'));
    }

    public function test_each_filter_and_malformed_values(): void
    {
        $other = Branch::factory()->create();
        $first = $this->order(['status' => OrderStatus::CONFIRMED, 'payment_status' => PaymentStatus::PAID, 'payment_method' => PaymentMethod::BANK_TRANSFER, 'total' => 500]);
        $second = $this->order(['branch_id' => $other->id, 'delivery_type' => DeliveryType::PICKUP, 'total' => 5]);

        $only = fn (string $query, Order $order) => $this->page('/orders?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $order->id));

        $only('filter[status]='.OrderStatus::CONFIRMED->value, $first);
        $only('filter[payment_status]='.PaymentStatus::PAID->value, $first);
        $only('filter[payment_method]='.PaymentMethod::BANK_TRANSFER->value, $first);
        $only('filter[delivery_type]='.DeliveryType::PICKUP->value, $second);
        $only('filter[branch_id]='.$other->id, $second);
        $only('filter[total_from]=100', $first);

        $this->page('/orders?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
        $this->page('/orders?filter[status]=abc')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
        $this->page('/orders?sort=-total')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.id', $first->id));
    }

    // ---------------------------------------------------------------- add

    public function test_the_add_page_offers_what_can_follow_pending(): void
    {
        $this->page('/orders/add')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Order/Add')
            ->has('submitToken')
            ->has('statuses', 4)
            ->where('statuses.0.value', OrderStatus::PENDING->value)
            ->has('districts')
            ->has('cities'));
    }

    public function test_add_computes_the_money_on_the_server(): void
    {
        $this->store(['items' => [['product_id' => $this->product->id, 'quantity' => 3]], 'delivery_fee' => 15])
            ->assertRedirect($this->url('/orders'))
            ->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertEquals(300, $order->sub_total);
        $this->assertEquals(15, $order->delivery_fee);
        $this->assertEquals(315, $order->total);
        $this->assertEquals(0, $order->discount);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame($this->staff->id, (int) $order->created_by);
        $this->assertStringEndsWith((string) $order->id, $order->order_number);
        $this->assertEquals(100, $order->items[0]->price);
        $this->assertEquals(300, $order->items[0]->total_price);
    }

    public function test_a_total_sent_by_the_browser_is_ignored(): void
    {
        $this->store([
            'sub_total' => 1,
            'discount' => 999,
            'total' => 1,
            'grand_total' => 1,
            'order_number' => 'HACKED',
            'created_by' => $this->customer->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 0.01, 'total_price' => 0.02]],
        ])->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertEquals(200, $order->sub_total);
        $this->assertEquals(0, $order->discount);
        $this->assertEquals(200, $order->total);
        $this->assertNotSame('HACKED', $order->order_number);
        $this->assertSame($this->staff->id, (int) $order->created_by);
        $this->assertEquals(100, $order->items[0]->price);
        $this->assertEquals(200, $order->items[0]->total_price);
    }

    public function test_the_price_is_the_products_current_one_with_its_sale_and_discount(): void
    {
        $sale = Product::factory()->create(['base_price' => 100, 'sale_price' => 80]);
        $discounted = Product::factory()->create(['base_price' => 100, 'sale_price' => null, 'discount_type' => 'percentage', 'discount_value' => 10]);

        $this->store(['items' => [['product_id' => $sale->id, 'quantity' => 1], ['product_id' => $discounted->id, 'quantity' => 1]]])->assertSessionHasNoErrors();

        $this->assertEquals(170, Order::query()->firstOrFail()->total);
    }

    public function test_a_fee_only_applies_to_a_delivered_order(): void
    {
        $this->store(['delivery_type' => DeliveryType::PICKUP->value, 'delivery_fee' => 50])->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $this->assertEquals(0, $order->delivery_fee);
        $this->assertEquals(100, $order->total);
    }

    public function test_double_submit_does_not_create_duplicates(): void
    {
        $token = (string) Str::uuid();

        $this->store(['submit_token' => $token])->assertRedirect($this->url('/orders'))->assertSessionHasNoErrors();
        $this->store(['submit_token' => $token])->assertRedirect($this->url('/orders'))->assertSessionHasNoErrors();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, OrderItem::query()->count());
    }

    public function test_a_refused_form_can_be_sent_again_with_its_token(): void
    {
        $token = (string) Str::uuid();

        $this->store(['submit_token' => $token, 'contact_phone' => ''])->assertSessionHasErrors('contact_phone');
        $this->store(['submit_token' => $token])->assertSessionHasNoErrors();

        $this->assertSame(1, Order::query()->count());
    }

    public function test_required_fields_and_a_nested_error(): void
    {
        $this->actingAs($this->staff)->post($this->url('/orders'), [])->assertSessionHasErrors([
            'submit_token', 'user_id', 'branch_id', 'city_id', 'district_id', 'items', 'status', 'payment_method', 'payment_status', 'delivery_address', 'contact_phone',
        ]);

        $this->store(['items' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 0],
        ]])->assertSessionHasErrors(['items.1.product_id', 'items.1.quantity'])
            ->assertSessionDoesntHaveErrors(['items.0.product_id']);

        $this->store(['items' => [['quantity' => 1]]])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_district_must_be_in_the_city(): void
    {
        [, $elsewhere] = $this->cityWithDistrict();

        $this->store(['district_id' => $elsewhere->id])->assertSessionHasErrors('district_id');
    }

    public function test_a_deleted_product_cannot_be_ordered(): void
    {
        $this->product->delete();

        $this->store()->assertSessionHasErrors('items.0.product_id');
    }

    public function test_a_new_order_can_only_start_from_what_follows_pending(): void
    {
        $this->store(['status' => OrderStatus::DELIVERED->value])->assertSessionHasErrors('status');
        $this->assertSame(0, Order::query()->count());

        $this->store(['status' => OrderStatus::CONFIRMED->value])->assertSessionHasNoErrors();
        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_location_is_saved(): void
    {
        $this->store(['latitude' => 32.2211, 'longitude' => 35.2544])->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $this->assertEquals(32.2211, (float) $order->latitude);
        $this->assertEquals(35.2544, (float) $order->longitude);

        $this->store(['latitude' => 95, 'longitude' => 35])->assertSessionHasErrors('latitude');
        $this->store(['latitude' => 32])->assertSessionHasErrors('longitude');
    }

    public function test_a_total_too_large_for_the_column_is_a_validation_error(): void
    {
        $dear = Product::factory()->create(['base_price' => 99999999, 'sale_price' => null]);

        $this->store(['items' => [['product_id' => $dear->id, 'quantity' => 100]]])->assertSessionHasErrors('items');
    }

    // ---------------------------------------------------------------- edit

    public function test_the_edit_page_sends_the_order_and_what_its_status_can_become(): void
    {
        $order = $this->order(['status' => OrderStatus::CONFIRMED, 'discount' => 5]);
        $order->items()->create(['product_id' => $this->product->id, 'price' => 75, 'quantity' => 2, 'total_price' => 150]);

        $this->page('/orders/'.$order->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Order/Edit')
            ->where('order.id', $order->id)
            ->where('order.status', OrderStatus::CONFIRMED->value)
            ->where('order.items.0.product_id', $this->product->id)
            ->where('order.items.0.quantity', 2)
            ->where('selectedCustomer.label', 'Ahmad Customer')
            ->where('selectedProducts.0.value', $this->product->id)
            ->has('statuses', 2)
            ->where('statuses.0.value', OrderStatus::CONFIRMED->value)
            ->where('statuses.1.value', OrderStatus::PREPARING->value)
            ->missing('order.created_by'));
    }

    public function test_edit_keeps_the_price_a_line_was_sold_at_and_recomputes_the_totals(): void
    {
        $order = $this->order(['discount' => 10, 'sub_total' => 1, 'total' => 1]);
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 75, 'quantity' => 1, 'total_price' => 75]);
        $removed = $order->items()->create(['product_id' => $this->product->id, 'price' => 75, 'quantity' => 1, 'total_price' => 75]);
        $new = Product::factory()->create(['base_price' => 20, 'sale_price' => null]);

        $this->update($order, [
            'delivery_fee' => 5,
            'total' => 1,
            'discount' => 0,
            'items' => [
                ['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 2, 'price' => 1],
                ['product_id' => $new->id, 'quantity' => 1, 'price' => 1],
            ],
        ])->assertRedirect($this->url('/orders'))->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->findOrFail($order->id);
        $this->assertEquals(75, $line->fresh()->price);
        $this->assertEquals(150, $line->fresh()->total_price);
        $this->assertSoftDeleted($removed);
        $this->assertEquals(20, $order->items->firstWhere('product_id', $new->id)->price);
        $this->assertEquals(170, $order->sub_total);
        $this->assertEquals(10, $order->discount);
        $this->assertEquals(165, $order->total);
    }

    public function test_changing_a_lines_product_takes_the_new_products_price(): void
    {
        $order = $this->order();
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 75, 'quantity' => 1, 'total_price' => 75]);
        $new = Product::factory()->create(['base_price' => 20, 'sale_price' => null]);

        $this->update($order, ['items' => [['id' => $line->id, 'product_id' => $new->id, 'quantity' => 1]]])->assertSessionHasNoErrors();

        $this->assertEquals(20, $line->fresh()->price);
    }

    public function test_edit_keeps_the_addons_chosen_on_a_line(): void
    {
        $order = $this->order();
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 10, 'quantity' => 1, 'total_price' => 16]);
        $line->addonOptions()->attach(AddonOption::factory()->create()->id, ['quantity' => 2, 'price' => 3]);

        $this->update($order, ['items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 2]]])->assertSessionHasNoErrors();

        $this->assertEquals(26, $line->fresh()->total_price); // 10 x 2 + 3 x 2
    }

    public function test_a_line_whose_product_was_deleted_stays_editable(): void
    {
        $gone = Product::factory()->create(['base_price' => 40]);
        $order = $this->order();
        $line = $order->items()->create(['product_id' => $gone->id, 'price' => 40, 'quantity' => 1, 'total_price' => 40]);
        $gone->forceDelete();

        $this->page('/orders/'.$order->id.'/edit')->assertOk();
        $this->page('/orders')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.items.0.name', null));

        $this->update($order, ['items' => [['id' => $line->id, 'product_id' => null, 'quantity' => 2]]])->assertSessionHasNoErrors();

        $this->assertEquals(80, $line->fresh()->total_price);
    }

    public function test_a_line_of_another_order_cannot_be_taken_over(): void
    {
        $other = $this->order();
        $foreign = $other->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

        $this->update($this->order(), ['items' => [['id' => $foreign->id, 'quantity' => 5]]])->assertSessionHasErrors('items.0.id');

        $this->assertEquals(1, $foreign->fresh()->quantity);
    }

    // ---------------------------------------------------------------- status

    public function test_an_order_status_transition_is_refused(): void
    {
        $order = $this->order(['status' => OrderStatus::PENDING, 'sub_total' => 1, 'total' => 1, 'notes' => 'قبل']);
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

        Notification::fake();

        $this->update($order, [
            'status' => OrderStatus::DELIVERED->value,
            'notes' => 'بعد',
            'items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 9]],
        ])->assertSessionHasErrors('status');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
        $this->assertSame('قبل', $fresh->notes, 'nothing of the refused form is saved');
        $this->assertEquals(1, $line->fresh()->quantity);
        Notification::assertNothingSent();
    }

    public function test_a_finished_order_cannot_change_status(): void
    {
        foreach ([OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::REJECTED, OrderStatus::RETURNED] as $status) {
            $order = $this->order(['status' => $status]);
            $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

            $this->update($order, ['status' => OrderStatus::PENDING->value, 'items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 1]]])
                ->assertSessionHasErrors('status');

            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_an_allowed_transition_notifies_the_customer(): void
    {
        Notification::fake();

        $order = $this->order(['status' => OrderStatus::PENDING]);
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

        $this->update($order, ['status' => OrderStatus::CONFIRMED->value, 'items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 1]]])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        Notification::assertSentTo($this->customer, GeneralNotification::class);
    }

    public function test_no_notification_when_the_status_stays(): void
    {
        Notification::fake();

        $order = $this->order(['status' => OrderStatus::CONFIRMED]);
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

        $this->update($order, ['status' => OrderStatus::CONFIRMED->value, 'items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 1]]])
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_a_status_change_succeeds_without_firebase_credentials(): void
    {
        // Really sent, on a machine with no credentials configured: FcmChannel cannot even be built.
        config(['firebase.projects.app.credentials' => null]);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');

        $order = $this->order(['status' => OrderStatus::PENDING]);
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

        $this->update($order, ['status' => OrderStatus::CONFIRMED->value, 'items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 1]]])
            ->assertRedirect($this->url('/orders'))
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->customer->id)->count(), 'the database channel still ran');
    }

    public function test_a_failing_notification_does_not_fail_the_request(): void
    {
        $this->app->bind(\Illuminate\Notifications\Channels\DatabaseChannel::class, fn () => throw new RuntimeException('FCM is down'));
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
            $logged[] = $log->message;
        });

        $order = $this->order(['status' => OrderStatus::PENDING]);
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);

        $this->update($order, ['status' => OrderStatus::CONFIRMED->value, 'items' => [['id' => $line->id, 'product_id' => $this->product->id, 'quantity' => 1]]])
            ->assertRedirect($this->url('/orders'));

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertContains('Order status notification not sent: FCM is down', $logged);
    }

    // ---------------------------------------------------------------- lookups

    public function test_the_lookups_search_in_another_letter_case(): void
    {
        Product::factory()->create(['name' => 'Cotton Shirt', 'sku' => 'SKU-LOOK', 'base_price' => 50, 'sale_price' => null]);

        $this->actingAs($this->staff)->getJson($this->url('/orders/lookup/customers?q=AHMAD'))->assertOk()
            ->assertJsonPath('0.value', $this->customer->id)
            ->assertJsonPath('0.label', 'Ahmad Customer')
            ->assertJsonMissingPath('0.email');

        $this->actingAs($this->staff)->getJson($this->url('/orders/lookup/products?q=cOTTON'))->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.price', 50);

        $this->actingAs($this->staff)->getJson($this->url('/orders/lookup/products?q=sku-look'))->assertOk()->assertJsonCount(1);
        $this->actingAs($this->staff)->getJson($this->url('/orders/lookup/nonsense'))->assertNotFound();
    }

    public function test_the_lookup_leaves_out_variations(): void
    {
        $parent = Product::factory()->create(['name' => 'Parent']);
        Product::factory()->variationOf($parent)->create(['name' => 'Parent Red']);

        $this->actingAs($this->staff)->getJson($this->url('/orders/lookup/products?q=parent'))->assertOk()->assertJsonCount(1);
    }

    // ---------------------------------------------------------------- delete, info, permissions

    public function test_delete_and_bulk_delete_keep_the_record(): void
    {
        $one = $this->order();
        $two = $this->order();
        $three = $this->order();

        $this->actingAs($this->staff)->delete($this->url('/orders/'.$one->id))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($one);

        $this->actingAs($this->staff)->delete($this->url('/orders'), ['ids' => [$two->id]])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($two);
        $this->assertNotSoftDeleted($three);
    }

    public function test_info_and_a_bad_id(): void
    {
        $this->actingAs($this->staff)->getJson($this->url('/orders/'.$this->order()->id.'/info'))->assertOk()->assertJsonStructure(['activities']);
        $this->page('/orders/abc/edit')->assertNotFound();
        $this->page('/orders/999999/edit')->assertNotFound();
    }

    public function test_permission_denied(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@items.test', 'password' => 'secret-password']);
        $viewer->assignRole(Role::findOrCreate('Order Nothing '.Str::random(5), 'web'));
        $order = $this->order();

        $this->actingAs($viewer)->get($this->url('/orders'))->assertForbidden();
        $this->actingAs($viewer)->get($this->url('/orders/add'))->assertForbidden();
        $this->actingAs($viewer)->getJson($this->url('/orders/lookup/customers'))->assertForbidden();
        $this->actingAs($viewer)->getJson($this->url('/orders/edit-lookup/customers'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/orders/'.$order->id), [])->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('/orders/'.$order->id))->assertForbidden();
    }

    public function test_the_orders_api_lists_for_staff(): void
    {
        $this->order();

        $this->actingAs($this->staff, 'sanctum')->getJson($this->siteUrl('/api/v1/items/orders'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_pages_and_a_save_load_no_relation_lazily(): void
    {
        $order = $this->order();
        $line = $order->items()->create(['product_id' => $this->product->id, 'price' => 1, 'quantity' => 1, 'total_price' => 1]);
        $line->addonOptions()->attach(AddonOption::factory()->create()->id, ['quantity' => 1, 'price' => 1]);

        $lazy = [];
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$lazy): void {
            $lazy[] = $model::class.'::'.$relation;
        });

        $this->page('/orders')->assertOk();
        $this->page('/orders/add')->assertOk();
        $this->page('/orders/'.$order->id.'/edit')->assertOk();
        $this->actingAs($this->staff)->getJson($this->url('/orders/lookup/products'))->assertOk();
        $this->store()->assertSessionHasNoErrors();

        $this->assertSame([], array_values(array_unique($lazy)));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{0: City, 1: District}
     */
    private function cityWithDistrict(): array
    {
        $currency = Currency::query()->firstOrCreate(['code' => 'ILS'], ['name' => 'شيكل', 'symbol' => '₪']);
        $country = Country::query()->firstOrCreate(['code' => 'ZZ'], ['name' => 'بلد', 'phone_code' => '970', 'currency_id' => $currency->id]);
        $governorate = Governorate::query()->create(['name' => 'محافظة', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'مدينة', 'governorate_id' => $governorate->id]);
        $district = District::query()->create(['name' => 'حي', 'city_id' => $city->id]);

        return [$city, $district];
    }

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'order_number' => 'N-'.Str::random(10),
            'sub_total' => 0,
            'delivery_address' => 'عنوان',
            'contact_phone' => '0599000000',
            'status' => OrderStatus::PENDING,
            'payment_method' => PaymentMethod::CASH,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_type' => DeliveryType::DELIVERY,
            'created_by' => $this->staff->id,
            ...$attributes,
        ]);
    }

    private function fields(array $overrides = []): array
    {
        return [
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'delivery_fee' => 0,
            'status' => OrderStatus::PENDING->value,
            'payment_method' => PaymentMethod::CASH->value,
            'payment_status' => PaymentStatus::UNPAID->value,
            'delivery_type' => DeliveryType::DELIVERY->value,
            'delivery_address' => 'عنوان',
            'contact_phone' => '0599000000',
            ...$overrides,
        ];
    }

    private function store(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->post($this->url('/orders'), $this->fields(['submit_token' => (string) Str::uuid(), ...$overrides]));
    }

    private function update(Order $order, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->put($this->url('/orders/'.$order->id), $this->fields(['status' => $order->status->value, ...$overrides]));
    }

    private function page(string $path): TestResponse
    {
        return $this->actingAs($this->staff)->get($this->url($path));
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin'.$path);
    }
}
