<?php

namespace Modules\Wholesale\Support;

use Modules\Items\Entities\Product;

/**
 * The wholesale prices of one tier for a set of products, worked out without a database (built by
 * WholesalePricing::book). The rules, in order:
 *
 * 1. The item's own price in this tier (an override), when it has one;
 * 2. else its wholesale price, a variant's own or else its parent's (0 or empty is none), minus the
 *    tier's discount. No price at all: the item is not sold wholesale (null).
 * 3. Then the highest quantity break the product reaches, counted over all its variants in the
 *    order, comes off that price.
 * 4. Rounded to 2 decimals once, at the end. A price of 0 is not sold.
 */
final class PriceBook
{
    /** @var array<int, list<array{0: int, 1: float}>> group id => [min quantity, percent], lowest first */
    private array $breaks = [];

    /**
     * @param  float|null  $tierDiscount  the tier's percent off the wholesale price; null: no tier
     * @param  array<int, float|int|string>  $overrides  item id => its price in this tier
     * @param  array<int, list<array{0: int, 1: float|int|string}>>  $breaks  group id => [min quantity, percent]
     */
    public function __construct(
        private readonly ?float $tierDiscount,
        private readonly array $overrides,
        array $breaks,
    ) {
        foreach ($breaks as $groupId => $rows) {
            $rows = array_map(fn (array $row) => [(int) $row[0], (float) $row[1]], $rows);
            usort($rows, fn (array $a, array $b) => $a[0] <=> $b[0]);
            $this->breaks[(int) $groupId] = $rows;
        }
    }

    /**
     * The product the breaks belong to: a variant's parent, or the product itself.
     */
    public static function groupId(Product $item): int
    {
        return (int) ($item->parent_id ?: $item->id);
    }

    /**
     * The item's price in this tier before any quantity break, or null when it is not sold wholesale.
     */
    public function base(Product $item): ?float
    {
        return self::sold($this->raw($item));
    }

    /**
     * What one piece costs when the order holds $groupQuantity pieces of the item's product.
     */
    public function unit(Product $item, int $groupQuantity): ?float
    {
        $raw = $this->raw($item);

        if ($raw === null) {
            return null;
        }

        return self::sold($raw * (1 - $this->breakPercent(self::groupId($item), $groupQuantity) / 100));
    }

    /**
     * The price rounded once, or null when nothing is left of it: a wholesale price of 0 (a 100 %
     * discount, or one that rounds away) is never sold, as goods are never given away.
     */
    private static function sold(?float $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $price = round($raw, 2);

        return $price > 0 ? $price : null;
    }

    /**
     * The percent of the highest break $quantity reaches, 0 when none.
     */
    public function breakPercent(int $groupId, int $quantity): float
    {
        $percent = 0.0;

        foreach ($this->breaks[$groupId] ?? [] as [$min, $value]) {
            if ($quantity >= $min) {
                $percent = $value;
            }
        }

        return min(max($percent, 0.0), 100.0);
    }

    /**
     * @return list<array{min_quantity: int, discount_percent: float}>
     */
    public function breaksFor(int $groupId): array
    {
        return array_map(fn (array $row) => ['min_quantity' => $row[0], 'discount_percent' => $row[1]], $this->breaks[$groupId] ?? []);
    }

    private function raw(Product $item): ?float
    {
        $override = $this->overrides[(int) $item->id] ?? null;

        if ($override !== null && (float) $override > 0) {
            return (float) $override;
        }

        $price = self::positive($item->wholesale_price);

        if ($price === null && $item->parent_id) {
            $price = self::positive($item->parent?->wholesale_price);
        }

        if ($price === null) {
            return null;
        }

        return $price * (1 - min(max((float) $this->tierDiscount, 0.0), 100.0) / 100);
    }

    private static function positive(mixed $value): ?float
    {
        return $value !== null && $value !== '' && (float) $value > 0 ? (float) $value : null;
    }
}
