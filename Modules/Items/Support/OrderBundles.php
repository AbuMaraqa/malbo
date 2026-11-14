<?php

namespace Modules\Items\Support;

use Modules\Items\Entities\OrderItem;

/**
 * The set or bundle an order line was sold in, as its snapshot keeps it (OrderService::bundleLines):
 * what the order pages, documents and Meta events show for its pieces.
 */
final class OrderBundles
{
    /**
     * The set's name in $locale, else in any language; null for a line sold alone.
     */
    public static function name(OrderItem $item, string $locale): ?string
    {
        if ($item->bundle_group === null || ! is_array($item->bundle)) {
            return null;
        }

        $names = (array) ($item->bundle['name'] ?? []);

        return (string) ($names[$locale] ?? collect($names)->first(fn ($name) => filled($name)) ?? '');
    }

    /**
     * "Summer set ×2": the set and how many of it the order holds.
     */
    public static function label(OrderItem $item, string $locale): ?string
    {
        $name = self::name($item, $locale);

        return $name === null ? null : trim($name.' ×'.(int) ($item->bundle['quantity'] ?? 1));
    }

    public static function quantity(OrderItem $item): int
    {
        return (int) ($item->bundle['quantity'] ?? 1);
    }

    public static function unitPrice(OrderItem $item): float
    {
        return (float) ($item->bundle['unit_price'] ?? 0);
    }
}
