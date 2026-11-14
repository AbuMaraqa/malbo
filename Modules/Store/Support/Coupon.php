<?php

namespace Modules\Store\Support;

use Modules\Items\Enums\OfferType;

/**
 * A coupon that applies to an order: its code as saved, what it takes off, and the total of the
 * lines it covers (the lines of its offer's product or category).
 */
final class Coupon
{
    public function __construct(
        public readonly int $offerId,
        public readonly string $code,
        public readonly OfferType $type,
        public readonly float $value,
        public readonly float $matchingTotal,
    ) {}

    /**
     * The discount on the lines it covers: a percentage of them, or a fixed amount never more than
     * them. Free shipping takes nothing off.
     */
    public function discount(): float
    {
        return $this->discountFor($this->matchingTotal);
    }

    public function discountFor(float $total): float
    {
        $discount = match ($this->type) {
            OfferType::PERCENTAGE => $total * min(max($this->value, 0), 100) / 100,
            OfferType::FIXED => max($this->value, 0),
            OfferType::FREE_SHIPPING => 0,
        };

        return round(min($discount, max($total, 0)), 2);
    }

    public function freeShipping(): bool
    {
        return $this->type === OfferType::FREE_SHIPPING;
    }
}
