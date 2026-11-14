<?php

namespace Modules\Wholesale\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Items\Entities\Product;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Support\PriceBook;
use Modules\Wholesale\Support\WholesaleItems;

/**
 * What the portal shows an approved merchant: catalog cards and the size-run grid of a product,
 * priced for the merchant's tier. Only ever called behind `merchant.approved`.
 */
class WholesaleCatalog
{
    public function __construct(
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly WholesalePricing $pricing,
        private readonly StorefrontCatalog $storefront,
    ) {}

    /**
     * @param  array{q?: ?string, category?: ?string}  $filters
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, total: int, prev_url: ?string, next_url: ?string}
     */
    public function listing(Merchant $merchant, array $filters): array
    {
        $tier = $this->pricing->tierFor($merchant);
        $page = $this->products->catalog($tier?->id, $filters);
        $products = collect($page->items());
        $book = $this->pricing->book($tier, $products->flatMap(fn (Product $product) => WholesaleItems::of($product)));

        return [
            'data' => $products->map(fn (Product $product) => $this->card($product, $book))->filter()->values()->all(),
            ...self::pages($page),
        ];
    }

    /**
     * Root categories, for the catalog's filter.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function categories(): array
    {
        return array_map(fn (array $category) => ['slug' => (string) $category['slug'], 'name' => (string) $category['name']], $this->storefront->categoryList());
    }

    /**
     * The product page: a row per color, a cell per size, each with what is available now, its unit
     * price for the tier (before quantity breaks), the suggested retail price and the pieces of it
     * already in the cart. Null when nothing of it is sold wholesale to this tier.
     *
     * @param  array<int, int>  $inCart  item id => pieces
     * @return array<string, mixed>|null
     */
    public function page(Product $product, Merchant $merchant, array $inCart): ?array
    {
        $tier = $this->pricing->tierFor($merchant);
        $items = WholesaleItems::of($product);
        $book = $this->pricing->book($tier, $items);

        if (! collect($items)->contains(fn (Product $item) => $book->base($item) !== null)) {
            return null;
        }

        $sizes = [];
        $rows = [];

        foreach ($items as $item) {
            $size = WholesaleItems::sizeLabel($item) ?? '';
            $sizes[$size] ??= WholesaleItems::sizeSort($item);
        }

        uksort($sizes, fn ($a, $b) => $sizes[$a] <=> $sizes[$b]);
        $columns = array_keys($sizes);

        foreach ($items as $item) {
            $key = WholesaleItems::colorKey($item);
            $color = WholesaleItems::colorValue($item);

            $rows[$key] ??= [
                'key' => $key,
                'name' => $color ? (string) $color->name : null,
                'swatch' => $color && preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $color->color_code) ? $color->color_code : null,
                'sort' => [(int) ($color?->sort_order ?? 0), $key],
                'cells' => array_fill_keys(array_map('strval', $columns), null),
            ];

            $price = $book->base($item);
            $limit = WholesaleItems::limit($item);

            $rows[$key]['cells'][(string) (WholesaleItems::sizeLabel($item) ?? '')] = [
                'id' => (int) $item->id,
                'sku' => $item->sku,
                'size' => WholesaleItems::sizeLabel($item),
                'orderable' => $price !== null && $limit !== 0,
                'available' => $limit,
                // In stock now; what is ordered beyond it is a pre-order of the incoming goods.
                'in_stock' => WholesaleItems::inStock($item),
                'incoming' => WholesaleItems::incoming($item),
                'unit_price' => $price,
                'unit_label' => $price !== null ? (new Price($price))->format() : null,
                'retail_label' => $this->pricing->suggestedRetail($item)->format(),
                'in_cart' => (int) ($inCart[(int) $item->id] ?? 0),
            ];
        }

        uasort($rows, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        $prices = collect($items)->map(fn (Product $item) => $book->base($item))->filter(fn ($price) => $price !== null);
        $groupId = PriceBook::groupId($items[0]);

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => filled($product->short_description) ? (string) $product->short_description : null,
            'image' => $product->getFirstMediaUrl('product') ?: null,
            'by_piece' => (bool) ($product->wholesale_by_piece ?? true),
            'price_label' => (new Price((float) $prices->min()))->format(),
            'price_from' => $prices->unique()->count() > 1,
            'retail_label' => $this->retailFrom($items),
            'tier' => $this->pricing->tierFor($merchant)?->name,
            'sizes' => array_map(fn (string $size) => $size === '' ? null : $size, $columns),
            'rows' => array_values(array_map(function (array $row) {
                unset($row['sort']);
                $row['cells'] = array_values($row['cells']);

                return $row;
            }, $rows)),
            'breaks' => array_map(fn (array $break) => [
                ...$break,
                'label' => __('From :count pieces: :percent% off', ['count' => $break['min_quantity'], 'percent' => rtrim(rtrim(number_format($break['discount_percent'], 2), '0'), '.')]),
            ], $book->breaksFor($groupId)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function card(Product $product, PriceBook $book): ?array
    {
        $items = WholesaleItems::of($product);
        $prices = collect($items)->map(fn (Product $item) => $book->base($item))->filter(fn ($price) => $price !== null);

        if ($prices->isEmpty()) {
            return null;
        }

        $priced = collect($items)->filter(fn (Product $item) => $book->base($item) !== null);

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'image' => $product->getFirstMediaUrl('product') ?: null,
            'url' => route('store.wholesale.product', ['slug' => $product->slug]),
            'price_label' => (new Price((float) $prices->min()))->format(),
            'price_from' => $prices->unique()->count() > 1,
            'retail_label' => $this->retailFrom($items),
            'available' => $priced->contains(fn (Product $item) => WholesaleItems::limit($item) !== 0),
            'by_piece' => (bool) ($product->wholesale_by_piece ?? true),
        ];
    }

    /**
     * The lowest storefront price of the items.
     *
     * @param  list<Product>  $items
     */
    private function retailFrom(array $items): string
    {
        return (new Price((float) collect($items)->map(fn (Product $item) => $this->pricing->suggestedRetail($item)->amount)->min()))->format();
    }

    /**
     * @return array{current_page: int, last_page: int, total: int, prev_url: ?string, next_url: ?string}
     */
    private static function pages(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'prev_url' => $page->previousPageUrl(),
            'next_url' => $page->nextPageUrl(),
        ];
    }
}
