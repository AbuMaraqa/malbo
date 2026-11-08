<?php

namespace Modules\Store\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Items\Entities\Product;
use Modules\Store\Support\Price;

/**
 * What a product costs on the storefront: the sale price when it is above 0 and lower than the base price,
 * then the product's percentage or fixed discount while its date window is open.
 */
class PricingService
{
    public function for(Product $product, ?CarbonInterface $at = null): Price
    {
        $base = (float) $product->base_price;
        $sale = (float) $product->sale_price > 0 ? (float) $product->sale_price : null; // 0 or empty: no sale

        $amount = $sale !== null && $sale < $base ? $sale : $base;

        if ($this->discountIsOpen($product, $at ?? Carbon::now())) {
            $value = (float) $product->discount_value;
            $amount -= match ($product->discount_type) {
                'percentage' => $amount * min($value, 100) / 100,
                'fixed' => $value,
                default => 0,
            };
        }

        $amount = round(max($amount, 0), 2);

        return new Price($amount, $amount < $base ? round($base, 2) : null);
    }

    private function discountIsOpen(Product $product, CarbonInterface $at): bool
    {
        if (! in_array($product->discount_type, ['percentage', 'fixed'], true) || (float) $product->discount_value <= 0) {
            return false;
        }

        $start = $product->discount_start_date ? Carbon::parse($product->discount_start_date) : null;
        $end = $product->discount_end_date ? Carbon::parse($product->discount_end_date) : null;
        // The admin picks a date: a midnight end means "through that day".
        if ($end && $end->format('H:i:s') === '00:00:00') {
            $end = $end->endOfDay();
        }

        return ($start === null || $at->gte($start)) && ($end === null || $at->lte($end));
    }
}
