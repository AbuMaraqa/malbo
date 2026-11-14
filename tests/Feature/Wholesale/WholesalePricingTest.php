<?php

namespace Tests\Feature\Wholesale;

use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Services\WholesalePricing;

/**
 * WholesalePricing against the database: which tier a merchant gets and what a PriceBook loads.
 */
class WholesalePricingTest extends WholesaleTestCase
{
    public function test_merchant_tier_else_default_else_none(): void
    {
        $pricing = app(WholesalePricing::class);
        $gold = $this->tier('ذهبي', 20);

        $this->assertNull($pricing->tierFor($this->merchant()));
        $this->assertSame($gold->id, $pricing->tierFor($this->merchant(attributes: ['tier_id' => $gold->id]))->id);

        $default = $this->tier('أساسي', 5, default: true);
        $this->assertSame($default->id, $pricing->tierFor($this->merchant())->id);
    }

    public function test_book_loads_overrides_and_breaks_for_the_tier(): void
    {
        [$shirt, $variants] = $this->shirt();
        $gold = $this->tier('ذهبي', 10);
        $silver = $this->tier('فضي', 0);

        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $gold->id, 'price' => 41]);
        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $silver->id, 'price' => 47]);
        QuantityBreak::query()->create(['product_id' => $shirt->id, 'min_quantity' => 12, 'discount_percent' => 5]);

        $items = collect($variants)->each(fn ($variant) => $variant->setRelation('parent', $shirt))->values();
        $book = app(WholesalePricing::class)->book($gold, $items);

        $this->assertSame(41.0, $book->base($variants['black-S']));
        $this->assertSame(45.0, $book->base($variants['white-M']));   // 50 - 10 %
        $this->assertSame(42.75, $book->unit($variants['white-M'], 12)); // then - 5 %
        $this->assertSame(50.0, app(WholesalePricing::class)->book(null, $items)->base($variants['white-M']));
    }

    public function test_suggested_retail_is_the_storefront_price(): void
    {
        $belt = $this->single(['base_price' => 60, 'sale_price' => 45]);

        $this->assertSame(45.0, app(WholesalePricing::class)->suggestedRetail($belt)->amount);
    }
}
