<?php

namespace Tests\Unit\Wholesale;

use Modules\Items\Entities\Product;
use Modules\Wholesale\Support\PriceBook;
use Tests\TestCase;

/**
 * The wholesale price rules, without a database: the tier override, else the wholesale price (a
 * variant's, else its parent's) minus the tier's discount; then the highest quantity break the
 * product's whole quantity reaches; rounded to 2 decimals once.
 */
class PriceBookTest extends TestCase
{
    public function test_override_wins(): void
    {
        $item = $this->item(1, wholesale: 50);
        $book = new PriceBook(10, [1 => 42.5], []);

        $this->assertSame(42.5, $book->base($item));
        $this->assertSame(42.5, $book->unit($item, 1));
    }

    public function test_wholesale_price_minus_tier_discount(): void
    {
        $item = $this->item(1, wholesale: 80);

        $this->assertSame(72.0, (new PriceBook(10, [], []))->base($item));
        $this->assertSame(80.0, (new PriceBook(0, [], []))->base($item));
    }

    public function test_a_variant_falls_back_to_its_parent_price(): void
    {
        $parent = $this->item(1, wholesale: 60);
        $variant = $this->item(2, wholesale: null, parent: $parent);
        $own = $this->item(3, wholesale: 70, parent: $parent);

        $book = new PriceBook(50, [], []);

        $this->assertSame(30.0, $book->base($variant));
        $this->assertSame(35.0, $book->base($own));
    }

    public function test_no_price_means_not_sold_wholesale(): void
    {
        $parent = $this->item(1, wholesale: null);
        $variant = $this->item(2, wholesale: null, parent: $parent);
        $book = new PriceBook(0, [], [1 => [[1, 10.0]]]);

        $this->assertNull($book->base($variant));
        $this->assertNull($book->unit($variant, 100));
        $this->assertNull($book->base($this->item(3, wholesale: null)));
    }

    public function test_zero_is_no_price(): void
    {
        $parent = $this->item(1, wholesale: 0);
        $variant = $this->item(2, wholesale: 0, parent: $parent);

        $this->assertNull((new PriceBook(0, [], []))->base($variant));
    }

    public function test_an_override_on_an_item_without_a_wholesale_price_sells_it(): void
    {
        $this->assertSame(25.0, (new PriceBook(10, [5 => 25], []))->base($this->item(5, wholesale: null)));
    }

    public function test_the_highest_break_reached_applies_on_top(): void
    {
        $item = $this->item(1, wholesale: 100);
        $book = new PriceBook(10, [], [1 => [[12, 5.0], [24, 10.0], [6, 2.0]]]);

        $this->assertSame(90.0, $book->unit($item, 5));      // no break: 100 - 10 %
        $this->assertSame(88.2, $book->unit($item, 6));      // 90 - 2 %
        $this->assertSame(85.5, $book->unit($item, 12));     // 90 - 5 %
        $this->assertSame(85.5, $book->unit($item, 23));
        $this->assertSame(81.0, $book->unit($item, 24));     // 90 - 10 %
        $this->assertSame(10.0, $book->breakPercent(1, 500));
        $this->assertSame(0.0, $book->breakPercent(1, 0));
    }

    public function test_breaks_belong_to_the_parent_and_count_its_whole_quantity(): void
    {
        $parent = $this->item(1, wholesale: 40);
        $small = $this->item(2, wholesale: null, parent: $parent);
        $book = new PriceBook(0, [], [1 => [[10, 25.0]]]);

        $this->assertSame(1, PriceBook::groupId($small));
        $this->assertSame(40.0, $book->unit($small, 9));
        // The caller passes the product's total across its variants.
        $this->assertSame(30.0, $book->unit($small, 10));
    }

    public function test_override_with_a_break(): void
    {
        $parent = $this->item(1, wholesale: 40);
        $variant = $this->item(2, wholesale: null, parent: $parent);
        $book = new PriceBook(10, [2 => 50], [1 => [[3, 20.0]]]);

        $this->assertSame(40.0, $book->unit($variant, 3));
    }

    public function test_rounding_happens_once(): void
    {
        // 19.99 - 33 % = 13.3933, then - 7 % = 12.455769 → 12.46. Rounding the tier price first
        // (13.39) would give 13.39 × 0.93 = 12.4527 → 12.45.
        $item = $this->item(1, wholesale: 19.99);
        $book = new PriceBook(33, [], [1 => [[2, 7.0]]]);

        $this->assertSame(13.39, $book->base($item));
        $this->assertSame(12.46, $book->unit($item, 2));
    }

    public function test_a_tier_discount_below_0_counts_as_0(): void
    {
        $this->assertSame(50.0, (new PriceBook(-20, [], []))->base($this->item(1, wholesale: 50)));
    }

    public function test_a_discount_of_100_or_more_means_not_sold(): void
    {
        $item = $this->item(1, wholesale: 50);

        // A price of 0 is never sold: no free goods.
        $this->assertNull((new PriceBook(100, [], []))->base($item));
        $this->assertNull((new PriceBook(150, [], []))->base($item));
        $this->assertNull((new PriceBook(0, [], [1 => [[2, 100.0]]]))->unit($item, 2));
        $this->assertNull((new PriceBook(0, [], [1 => [[2, 130.0]]]))->unit($item, 2));
        $this->assertNull((new PriceBook(0, [1 => 50], [1 => [[2, 100.0]]]))->unit($item, 2));
    }

    public function test_a_price_that_rounds_to_0_is_not_sold(): void
    {
        $this->assertNull((new PriceBook(99.99, [], []))->base($this->item(1, wholesale: 0.04)));
    }

    public function test_a_break_below_0_counts_as_0(): void
    {
        $this->assertSame(50.0, (new PriceBook(0, [], [1 => [[2, -10.0]]]))->unit($this->item(1, wholesale: 50), 2));
    }

    public function test_no_tier_means_no_discount(): void
    {
        $this->assertSame(19.99, (new PriceBook(null, [], []))->base($this->item(1, wholesale: 19.99)));
    }

    public function test_breaks_are_listed_lowest_first(): void
    {
        $book = new PriceBook(0, [], [1 => [[24, 10.0], [12, 5.0]]]);

        $this->assertSame([['min_quantity' => 12, 'discount_percent' => 5.0], ['min_quantity' => 24, 'discount_percent' => 10.0]], $book->breaksFor(1));
        $this->assertSame([], $book->breaksFor(9));
    }

    private function item(int $id, ?float $wholesale, ?Product $parent = null): Product
    {
        $product = new Product;
        $product->forceFill(['id' => $id, 'wholesale_price' => $wholesale, 'parent_id' => $parent?->id]);

        if ($parent) {
            $product->setRelation('parent', $parent);
        }

        return $product;
    }
}
