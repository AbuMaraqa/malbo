<?php

namespace Tests\Unit\Items;

use Modules\Items\Support\BundleAllocation;
use PHPUnit\Framework\TestCase;

/**
 * A bundle's price shared out over its components, so each order line carries what it sold for
 * (reports by product, colour and size; refunds of a returned piece).
 */
class BundleAllocationTest extends TestCase
{
    public function test_proportional_in_cents(): void
    {
        // Shirt 120, trousers 180 (300 together) sold as a set for 240.
        $split = BundleAllocation::split(240, [
            'shirt' => ['quantity' => 1, 'weight' => 120],
            'trousers' => ['quantity' => 1, 'weight' => 180],
        ]);

        $this->assertSame(['shirt' => ['unit' => 96.0, 'extra' => 0.0], 'trousers' => ['unit' => 144.0, 'extra' => 0.0]], $split);
    }

    public function test_rounding_sums_exactly(): void
    {
        $components = [
            'a' => ['quantity' => 2, 'weight' => 10],
            'b' => ['quantity' => 3, 'weight' => 10],
            'c' => ['quantity' => 1, 'weight' => 33.33],
        ];

        foreach ([100.01, 99.99, 0.07, 1234.56, 0.0] as $price) {
            $split = BundleAllocation::split($price, $components);
            $total = 0;

            foreach ($split as $key => $line) {
                $this->assertGreaterThanOrEqual(0, $line['unit']);
                $this->assertSame($line['unit'], round($line['unit'], 2), 'whole cents');
                $total += (int) round($line['unit'] * 100) * $components[$key]['quantity'] + (int) round($line['extra'] * 100);
            }

            $this->assertSame((int) round($price * 100), $total, "the lines of {$price} add up to it");
        }

        // Only quantities above what is left: the cent that cannot be spread goes to one line's total.
        $split = BundleAllocation::split(0.01, ['x' => ['quantity' => 2, 'weight' => 1], 'y' => ['quantity' => 3, 'weight' => 1]]);
        $this->assertSame(0.01, round(array_sum(array_column($split, 'extra')), 2));
    }

    public function test_zero_weights_split_by_quantity(): void
    {
        $split = BundleAllocation::split(90, ['a' => ['quantity' => 1, 'weight' => 0], 'b' => ['quantity' => 2, 'weight' => 0]]);

        $this->assertSame(30.0, $split['a']['unit']);
        $this->assertSame(30.0, $split['b']['unit']);
    }
}
