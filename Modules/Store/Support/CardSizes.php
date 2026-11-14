<?php

namespace Modules\Store\Support;

use Illuminate\Support\Collection;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Store\Services\ProductConfigurator;

/**
 * The sizes a product card lists: the values of the size attribute (AttributeRepository::sizeAttributeId)
 * on the active variants a buyer can see (ProductConfigurator::visible), in the values' order. A size
 * none of those variants can order now (ProductConfigurator::available) stays in the list, marked
 * unavailable: the card strikes it through. Plain values, cached with the card's facts; a stock write
 * saves the variant, which renews the cache.
 */
final class CardSizes
{
    /**
     * Expects the card relations: active variations with their stock columns and their values (id,
     * is_active, attribute_id, sort_order, translations).
     *
     * @return list<array{name: string, available: bool}>
     */
    public static function facts(Product $product, ?int $sizeAttributeId): array
    {
        if ($sizeAttributeId === null || ! $product->relationLoaded('variations')) {
            return [];
        }

        /** @var array<int, array{name: string, available: bool, sort: array{0: int, 1: int}}> $sizes */
        $sizes = [];

        foreach ($product->variations as $variation) {
            if (! ProductConfigurator::visible($variation)) {
                continue;
            }

            $available = ProductConfigurator::available($variation);

            foreach (self::sizeValues($variation, $sizeAttributeId) as $value) {
                $sizes[$value->id] ??= [
                    'name' => (string) ($value->name ?: $value->translations->first()?->name),
                    'available' => false,
                    'sort' => [(int) $value->sort_order, (int) $value->id],
                ];
                $sizes[$value->id]['available'] = $sizes[$value->id]['available'] || $available;
            }
        }

        uasort($sizes, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_values(array_map(fn (array $size) => ['name' => $size['name'], 'available' => $size['available']], $sizes));
    }

    /**
     * @return Collection<int, AttributeValue>
     */
    private static function sizeValues(Product $variation, int $sizeAttributeId): Collection
    {
        return $variation->attributeValues->filter(fn (AttributeValue $value) => (int) $value->attribute_id === $sizeAttributeId);
    }
}
