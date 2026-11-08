<?php

namespace Tests\Unit\Store;

use Illuminate\Support\Carbon;
use Modules\Items\Entities\Product;
use Modules\Store\Services\PricingService;
use Modules\Store\Support\Price;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_plain_price(): void
    {
        $price = $this->price(['base_price' => '120.00']);

        $this->assertSame(120.0, $price->amount);
        $this->assertNull($price->was);
        $this->assertFalse($price->discounted());
    }

    public function test_sale_price_below_the_base_price_wins(): void
    {
        $price = $this->price(['base_price' => '120.00', 'sale_price' => '99.90']);

        $this->assertSame(99.9, $price->amount);
        $this->assertSame(120.0, $price->was);
        $this->assertTrue($price->discounted());
    }

    public function test_sale_price_not_below_the_base_price_is_ignored(): void
    {
        $price = $this->price(['base_price' => '120.00', 'sale_price' => '150.00']);

        $this->assertSame(120.0, $price->amount);
        $this->assertFalse($price->discounted());
    }

    public function test_percentage_discount_inside_its_window(): void
    {
        $price = $this->price([
            'base_price' => '200.00',
            'discount_type' => 'percentage',
            'discount_value' => '15.00',
            'discount_start_date' => '2026-10-01 00:00:00',
            'discount_end_date' => '2026-10-31 23:59:59',
        ], '2026-10-27 12:00:00');

        $this->assertSame(170.0, $price->amount);
        $this->assertSame(200.0, $price->was);
    }

    public function test_fixed_discount_applies_on_top_of_the_sale_price_with_open_ends(): void
    {
        $price = $this->price([
            'base_price' => '200.00',
            'sale_price' => '180.00',
            'discount_type' => 'fixed',
            'discount_value' => '30.00',
        ]);

        $this->assertSame(150.0, $price->amount);
        $this->assertSame(200.0, $price->was);
    }

    public function test_discount_outside_its_window_is_ignored(): void
    {
        $ended = $this->price(['base_price' => '100.00', 'discount_type' => 'fixed', 'discount_value' => '10.00', 'discount_end_date' => '2026-10-01 00:00:00'], '2026-10-27 12:00:00');
        $notStarted = $this->price(['base_price' => '100.00', 'discount_type' => 'fixed', 'discount_value' => '10.00', 'discount_start_date' => '2026-11-01 00:00:00'], '2026-10-27 12:00:00');

        $this->assertSame(100.0, $ended->amount);
        $this->assertSame(100.0, $notStarted->amount);
        $this->assertFalse($ended->discounted());
    }

    public function test_a_date_only_end_includes_that_whole_day(): void
    {
        $price = $this->price(['base_price' => '100.00', 'discount_type' => 'fixed', 'discount_value' => '10.00', 'discount_end_date' => '2026-10-31 00:00:00'], '2026-10-31 18:00:00');

        $this->assertSame(90.0, $price->amount);
    }

    public function test_zero_sale_price_is_no_sale(): void
    {
        $price = $this->price(['base_price' => '100.00', 'sale_price' => '0.00']);

        $this->assertSame(100.0, $price->amount);
        $this->assertFalse($price->discounted());
    }

    public function test_price_is_never_negative(): void
    {
        $price = $this->price(['base_price' => '20.00', 'discount_type' => 'fixed', 'discount_value' => '50.00']);

        $this->assertSame(0.0, $price->amount);
    }

    public function test_percentage_result_is_rounded_to_cents(): void
    {
        $price = $this->price(['base_price' => '9.99', 'discount_type' => 'percentage', 'discount_value' => '33.00']);

        $this->assertSame(6.69, $price->amount);
    }

    public function test_format(): void
    {
        $this->assertSame('120.00 ₪', (new Price(120))->format());
        $this->assertSame('1,250.50 $', (new Price(1250.5))->format('$'));
    }

    private function price(array $attributes, ?string $at = null): Price
    {
        $product = (new Product)->forceFill($attributes + ['discount_type' => 'none']);

        return app(PricingService::class)->for($product, $at ? Carbon::parse($at) : null);
    }
}
