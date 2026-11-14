<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Services\CheckoutService;
use Modules\Store\Services\CouponService;
use Modules\Store\Settings\StoreShippingSettings;
use Tests\DatabaseTestCase;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Offers with a code are coupons, applied at checkout to the lines of the offer's product or
 * category; the delivery fee comes from the city's zone, free above the zone's threshold or the store's.
 */
class CouponTest extends DatabaseTestCase
{
    use MakesShippingZones;

    private User $owner;

    /** The product the coupons of these tests point at, unless a test says otherwise. */
    private Product $target;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');
        $this->owner = User::forceCreate(['name' => 'Owner', 'email' => 'owner-'.Str::random(6).'@store.test', 'password' => 'secret-password']);
        $this->target = Product::factory()->create();
    }

    public function test_percentage_and_fixed_codes_lower_the_subtotal(): void
    {
        $this->coupon('TEN', OfferType::PERCENTAGE, 10);
        $this->coupon('FIFTY', OfferType::FIXED, 50);
        $city = $this->city();
        $this->zone([$city], ['fee' => 20]);

        $quote = $this->quote(200, $city->id, 'TEN');
        $this->assertSame([200.0, 20.0, 20.0, 200.0], [$quote['subtotal'], $quote['discount'], $quote['delivery_fee'], $quote['total']]);
        $this->assertTrue($quote['coupon']['valid']);
        $this->assertSame('TEN', $quote['coupon']['code']);

        $this->assertSame(170.0, $this->quote(200, $city->id, 'FIFTY')['total']);
        // Never more than the subtotal.
        $this->assertSame(20.0, $this->quote(30, $city->id, 'FIFTY')['total']);
    }

    public function test_free_shipping_code(): void
    {
        $this->coupon('SHIPFREE', OfferType::FREE_SHIPPING, 0);
        $city = $this->city();
        $this->zone([$city], ['fee' => 25]);

        $quote = $this->quote(100, $city->id, 'SHIPFREE');

        $this->assertSame([0.0, 0.0, 100.0], [$quote['discount'], $quote['delivery_fee'], $quote['total']]);
        $this->assertTrue($quote['free_shipping']);
    }

    public function test_codes_are_matched_in_any_letter_case(): void
    {
        $this->coupon('Summer10', OfferType::PERCENTAGE, 10);

        $this->assertSame('Summer10', app(CouponService::class)->check('  sUMMER10 ', $this->lines(100))->code);
    }

    public function test_each_refusal_has_its_message(): void
    {
        Carbon::setTestNow('2026-11-08 12:00:00');
        $this->coupon('OFF', OfferType::FIXED, 10, ['is_active' => false]);
        $this->coupon('SOON', OfferType::FIXED, 10, ['start_date' => '2026-11-09 00:00:00']);
        $this->coupon('OLD', OfferType::FIXED, 10, ['end_date' => '2026-11-07 00:00:00']);
        $this->coupon('TODAY', OfferType::FIXED, 10, ['end_date' => '2026-11-08 00:00:00']);
        $this->coupon('BIG', OfferType::FIXED, 10, ['min_purchase_amount' => 300]);

        $this->assertRefused('NOPE', 'رمز القسيمة غير صالح.');
        $this->assertRefused('OFF', 'رمز القسيمة غير صالح.');
        $this->assertRefused('SOON', 'هذه القسيمة لم تبدأ بعد.');
        $this->assertRefused('OLD', 'انتهت صلاحية هذه القسيمة.');
        $this->assertRefused('BIG', 'هذه القسيمة تتطلب طلبًا بقيمة 300.00 ₪ على الأقل.');
        // An end date with no time runs through that day.
        $this->assertSame(10.0, app(CouponService::class)->check('TODAY', $this->lines(100))->discount());

        $quote = $this->quote(100, null, 'OLD');
        $this->assertFalse($quote['coupon']['valid']);
        $this->assertSame('انتهت صلاحية هذه القسيمة.', $quote['coupon']['message']);
        $this->assertSame(0.0, $quote['discount']);
    }

    public function test_an_offer_without_a_code_is_no_coupon(): void
    {
        $this->coupon(null, OfferType::FIXED, 10);

        $this->assertRefused('', 'رمز القسيمة غير صالح.');
        $this->assertNull($this->quote(100, null, '')['coupon']);
    }

    public function test_a_used_up_coupon_is_refused(): void
    {
        $this->coupon('ONCE', OfferType::FIXED, 10, ['usage_limit' => 2, 'usage_limit_per_user' => 1]);

        $this->order('ONCE', '0599000001');
        $this->assertRefused('ONCE', 'لقد استخدمت هذه القسيمة من قبل.', '+970 599 000 001');
        $this->assertSame(10.0, app(CouponService::class)->check('ONCE', $this->lines(100), '0599000002')->discount());

        $this->order('ONCE', '0599000002');
        $this->assertRefused('ONCE', 'نفدت مرات استخدام هذه القسيمة.', '0599000003');
    }

    public function test_a_cancelled_order_gives_its_use_back(): void
    {
        $this->coupon('ONCE', OfferType::FIXED, 10, ['usage_limit' => 1]);
        $order = $this->order('ONCE', '0599000001');
        $this->assertRefused('ONCE', 'نفدت مرات استخدام هذه القسيمة.');

        $order->update(['status' => OrderStatus::CANCELLED]);

        $this->assertSame('ONCE', app(CouponService::class)->check('ONCE', $this->lines(100))->code);
    }

    public function test_the_zone_threshold_wins_over_the_store_default(): void
    {
        $settings = app(StoreShippingSettings::class);
        $settings->default_free_shipping_threshold = 300;
        $settings->save();

        $own = $this->city('أ');
        $default = $this->city('ب');
        $nowhere = $this->city('ج');
        $this->zone([$own], ['fee' => 20, 'free_shipping_threshold' => 150, 'eta_min_days' => 2, 'eta_max_days' => 4]);
        $this->zone([$default], ['fee' => 30]);
        $this->coupon('TEN', OfferType::FIXED, 10);

        $quote = $this->quote(150, $own->id);
        $this->assertTrue($quote['free_shipping']);
        $this->assertSame([0.0, 150.0], [$quote['delivery_fee'], $quote['total']]);
        $this->assertSame('2–4 أيام', $quote['zone']['eta']);

        // Measured after the discount: 140 is under 150.
        $quote = $this->quote(150, $own->id, 'TEN');
        $this->assertFalse($quote['free_shipping']);
        $this->assertSame(20.0, $quote['delivery_fee']);
        $this->assertSame('10.00 ₪', $quote['free_shipping_remaining_label']);

        $this->assertSame(30.0, $this->quote(299, $default->id)['delivery_fee']);
        $this->assertSame(0.0, $this->quote(300, $default->id)['delivery_fee']);

        $quote = $this->quote(100, $nowhere->id);
        $this->assertFalse($quote['deliverable']);
        $this->assertNull($quote['zone']);
    }

    public function test_a_product_coupon_discounts_only_its_product_and_variants(): void
    {
        $variant = Product::factory()->variationOf($this->target)->create();
        $other = Product::factory()->create();
        $this->coupon('HALF', OfferType::PERCENTAGE, 50);

        $quote = app(CheckoutService::class)->quote([self::line($variant, 100), self::line($other, 300)], null, 'HALF');

        $this->assertSame([400.0, 50.0, 350.0], [$quote['subtotal'], $quote['discount'], $quote['total']]);
    }

    public function test_a_category_coupon_covers_child_categories_and_variants_by_parent(): void
    {
        $men = Category::factory()->create();
        $shirts = Category::factory()->create(['parent_id' => $men->id]);
        $shirt = Product::factory()->create();
        $shirt->categories()->attach($shirts->id);
        $variant = Product::factory()->variationOf($shirt)->create();
        $outside = Product::factory()->create();
        $this->coupon('MEN20', OfferType::FIXED, 20, on: $men);

        $quote = app(CheckoutService::class)->quote([self::line($variant, 15), self::line($outside, 100)], null, 'MEN20');

        // A fixed amount never goes past the matching lines: 15, not 20.
        $this->assertSame([115.0, 15.0, 100.0], [$quote['subtotal'], $quote['discount'], $quote['total']]);
    }

    public function test_a_code_that_matches_no_line_is_refused(): void
    {
        $this->coupon('TEN', OfferType::PERCENTAGE, 10);

        $quote = app(CheckoutService::class)->quote([self::line(Product::factory()->create(), 100)], null, 'TEN');

        $this->assertFalse($quote['coupon']['valid']);
        $this->assertSame('هذا الرمز لا ينطبق على القطع الموجودة في سلتك.', $quote['coupon']['message']);
        $this->assertSame(0.0, $quote['discount']);
    }

    public function test_the_minimum_and_free_shipping_count_the_matching_lines_only(): void
    {
        $other = Product::factory()->create();
        $city = $this->city();
        $this->zone([$city], ['fee' => 25]);
        $this->coupon('BIG', OfferType::PERCENTAGE, 10, ['min_purchase_amount' => 150]);
        $this->coupon('SHIP', OfferType::FREE_SHIPPING, 0, ['min_purchase_amount' => 150]);

        // 100 of the target and 500 of something else: the minimum of 150 is not met.
        $lines = [self::line($this->target, 100), self::line($other, 500)];
        $this->assertFalse(app(CheckoutService::class)->quote($lines, $city->id, 'BIG')['coupon']['valid']);
        $this->assertSame(25.0, app(CheckoutService::class)->quote($lines, $city->id, 'SHIP')['delivery_fee']);

        $lines = [self::line($this->target, 200), self::line($other, 500)];
        $this->assertSame(20.0, app(CheckoutService::class)->quote($lines, $city->id, 'BIG')['discount']);
        $this->assertSame(0.0, app(CheckoutService::class)->quote($lines, $city->id, 'SHIP')['delivery_fee']);
    }

    private function quote(float $subtotal, ?int $cityId, ?string $code = null): array
    {
        return app(CheckoutService::class)->quote($this->lines($subtotal), $cityId, $code);
    }

    /**
     * One cart line of the target product, as CartService::lines() gives it.
     *
     * @return list<array<string, mixed>>
     */
    private function lines(float $total, ?Product $product = null): array
    {
        return [self::line($product ?? $this->target, $total)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function line(Product $product, float $total): array
    {
        $sellable = $product->parent ?? $product;

        return ['available' => true, 'total' => $total, 'sellable_id' => $sellable->id, 'category_ids' => $sellable->categories()->get()->modelKeys()];
    }

    private function assertRefused(string $code, string $message, ?string $phone = null): void
    {
        try {
            app(CouponService::class)->check($code, $this->lines(100), $phone);
            $this->fail("{$code} must be refused.");
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()['coupon']);
        }
    }

    private function coupon(?string $code, OfferType $type, float $value, array $attributes = [], Product|Category|null $on = null): Offer
    {
        $on ??= $this->target;

        return Offer::query()->create([
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'is_active' => true,
            'min_purchase_amount' => 0,
            'usage_limit' => 100,
            'usage_limit_per_user' => 5,
            'offerable_type' => $on->getMorphClass(),
            'offerable_id' => $on->id,
            'created_by' => $this->owner->id,
            ...$attributes,
        ]);
    }

    private function order(string $code, string $phone): Order
    {
        return Order::query()->create([
            'order_number' => 'C-'.Str::random(8),
            'sub_total' => 100,
            'total' => 90,
            'discount' => 10,
            'status' => OrderStatus::AWAITING_CONFIRMATION,
            'contact_phone' => $phone,
            'coupon_code' => $code,
        ]);
    }
}
