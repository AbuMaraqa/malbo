<?php

namespace Modules\Wholesale\Support;

use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Services\InventoryService;
use Modules\Store\Services\ProductConfigurator;

/**
 * What a product offers wholesale and how its variants line up in size runs. A product's items are
 * its visible, active variants, or the product itself when it has none. An item's color is its
 * value of a color attribute (none: one row); its size is the rest of its values. A size run of a
 * color is one piece of each of its sizes that can be ordered now.
 *
 * Expects a product from WholesaleProductRepository (active variations with their values loaded).
 */
final class WholesaleItems
{
    /**
     * @return list<Product>
     */
    public static function of(Product $product): array
    {
        // `variations` holds the active ones; `variations_count` (WholesaleProductRepository) all
        // that are not deleted. A product whose variations are all inactive sells nothing.
        $hasVariations = $product->getAttribute('variations_count') !== null
            ? (int) $product->getAttribute('variations_count') > 0
            : $product->variations->isNotEmpty();

        if (! $hasVariations) {
            return [$product];
        }

        return $product->variations
            ->filter(fn (Product $variation) => ProductConfigurator::visible($variation))
            ->each(fn (Product $variation) => $variation->setRelation('parent', $product))
            ->values()
            ->all();
    }

    /**
     * Pieces of the item a merchant can order now: what is in stock plus the incoming pieces nobody
     * reserved yet (a pre-order, InventoryService::reservableIncoming). Null when there is no limit
     * (stock not managed, or backorders allowed), 0 when it is not available.
     */
    public static function limit(Product $item): ?int
    {
        if (! $item->manage_stock || $item->allow_backorder) {
            return ProductConfigurator::available($item) ? null : 0;
        }

        return self::inStock($item) + self::incomingLeft($item);
    }

    /**
     * Pieces of the item in stock now (null: no limit). An order beyond them is a pre-order.
     */
    public static function inStock(Product $item): ?int
    {
        if (! $item->manage_stock || $item->allow_backorder) {
            return ProductConfigurator::available($item) ? null : 0;
        }

        return ProductConfigurator::available($item) ? max(0, (int) $item->stock_quantity) : 0;
    }

    /**
     * The incoming goods a merchant may still reserve, with their expected date, or null when none.
     *
     * @return array{quantity: int, date: string, label: string}|null
     */
    public static function incoming(Product $item): ?array
    {
        $left = $item->manage_stock && ! $item->allow_backorder ? self::incomingLeft($item) : 0;

        if ($left <= 0) {
            return null;
        }

        $date = $item->incoming_expected_at->toDateString();

        return ['quantity' => $left, 'date' => $date, 'label' => __('Incoming on :date', ['date' => $date])];
    }

    /**
     * Dated incoming pieces less those already reserved (the stock below 0).
     */
    private static function incomingLeft(Product $item): int
    {
        return max(0, InventoryService::reservableIncoming($item) + min(0, (int) $item->stock_quantity));
    }

    public static function colorValue(Product $item): ?AttributeValue
    {
        return $item->attributeValues
            ->first(fn (AttributeValue $value) => (int) $value->attribute?->type === AttributeType::COLOR->value);
    }

    /**
     * The row an item belongs to: its color value's id, or 0.
     */
    public static function colorKey(Product $item): int
    {
        return (int) (self::colorValue($item)?->id ?? 0);
    }

    /**
     * The item's size: its values other than the color, in attribute order ("L", "L / Slim").
     */
    public static function sizeLabel(Product $item): ?string
    {
        $color = self::colorValue($item);
        $values = $item->attributeValues
            ->reject(fn (AttributeValue $value) => $color && $value->id === $color->id)
            ->sortBy('attribute_id')
            ->map(fn (AttributeValue $value) => (string) $value->name);

        return $values->isEmpty() ? null : $values->implode(' / ');
    }

    /**
     * Sort key of a size: its values' sort order, then ids.
     *
     * @return list<int>
     */
    public static function sizeSort(Product $item): array
    {
        $color = self::colorValue($item);

        return $item->attributeValues
            ->reject(fn (AttributeValue $value) => $color && $value->id === $color->id)
            ->sortBy('attribute_id')
            ->flatMap(fn (AttributeValue $value) => [(int) $value->sort_order, (int) $value->id])
            ->values()
            ->all();
    }

    /**
     * Why these quantities (item id => pieces) are not whole size runs, or null when they are: per
     * color, either nothing, or the same number of every size that can be ordered, and none of a
     * size that cannot.
     *
     * @param  list<Product>  $items  self::of()
     * @param  callable(Product): bool  $orderable
     * @param  array<int, int>  $quantities
     */
    public static function runProblem(array $items, callable $orderable, array $quantities): ?string
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[self::colorKey($item)][] = $item;
        }

        foreach ($rows as $row) {
            $wanted = array_map(fn (Product $item) => (int) ($quantities[(int) $item->id] ?? 0), $row);

            if (array_sum($wanted) === 0) {
                continue;
            }

            $counts = [];

            foreach ($row as $index => $item) {
                if ($orderable($item)) {
                    $counts[] = $wanted[$index];
                } elseif ($wanted[$index] > 0) {
                    $counts = [-1];
                    break;
                }
            }

            if ($counts === [] || min($counts) < 1 || count(array_unique($counts)) > 1) {
                $color = self::colorValue($row[0]);

                return $color
                    ? __('This product is sold in full size runs: order the same number of every available size of :color.', ['color' => (string) $color->name])
                    : __('This product is sold in full size runs: order the same number of every available size.');
            }
        }

        return null;
    }
}
