<?php

namespace Modules\Items\Support;

/**
 * One bundle's price shared out over its components, in whole cents, so each order line carries
 * what its piece sold for (reports by product, colour and size; the refund of one returned piece).
 *
 * Each component gets a unit price in proportion to its weight (what the buyer would pay for that
 * piece alone) times its quantity; equal weights when none has one. Rounding goes to the components
 * with the largest remainders whose quantity still fits what is left. A cent that no quantity can
 * take (every quantity is above it) is the first component's `extra`: added to its line total once
 * per bundle. Units times quantities plus the extra always equal the price.
 */
final class BundleAllocation
{
    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, array{quantity: int, weight: float}>  $components
     * @return array<TKey, array{unit: float, extra: float}>
     */
    public static function split(float $price, array $components): array
    {
        $cents = (int) round($price * 100);
        $quantities = array_map(fn (array $component) => max(1, (int) $component['quantity']), $components);
        $weights = array_map(fn (array $component) => max(0.0, (float) $component['weight']), $components);

        $total = 0.0;
        foreach ($weights as $key => $weight) {
            $total += $weight * $quantities[$key];
        }

        if ($total <= 0) {
            $weights = array_map(fn () => 1.0, $weights);
            $total = (float) array_sum($quantities);
        }

        $units = [];
        $remainders = [];
        $left = $cents;

        foreach ($weights as $key => $weight) {
            $ideal = $cents * $weight / $total;
            $units[$key] = (int) floor($ideal + 1e-9);
            $remainders[$key] = $ideal - $units[$key];
            $left -= $units[$key] * $quantities[$key];
        }

        arsort($remainders);

        do {
            $moved = false;

            foreach (array_keys($remainders) as $key) {
                if ($left > 0 && $quantities[$key] <= $left) {
                    $units[$key]++;
                    $left -= $quantities[$key];
                    $moved = true;
                }
            }
        } while ($left > 0 && $moved);

        $first = array_key_first($components);
        $split = [];

        foreach ($components as $key => $_) {
            $split[$key] = ['unit' => round($units[$key] / 100, 2), 'extra' => $key === $first ? round($left / 100, 2) : 0.0];
        }

        return $split;
    }
}
