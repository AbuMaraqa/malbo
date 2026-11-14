<?php

namespace Modules\Store\Support;

use Modules\Items\Entities\Product;

/**
 * The id Meta knows an item by, the same in the catalog feed and in every Pixel or server event:
 * its SKU, else `P` and its product id (a product saved without a SKU still gets a stable id).
 */
final class CatalogIds
{
    public static function of(Product $product): string
    {
        return self::from($product->sku, (int) $product->id);
    }

    public static function from(?string $sku, int $productId): string
    {
        $sku = trim((string) $sku);

        return $sku !== '' ? $sku : 'P'.$productId;
    }
}
