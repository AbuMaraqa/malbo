<?php

namespace Modules\Store\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Support\BundleStock;
use Modules\Store\Support\Price;

/**
 * The badges of a storefront card and product page, in this order:
 * - the product's labels, in their own colors;
 * - new and best seller, the admin's flags;
 * - offer: the price the buyer sees is discounted (PricingService). A product with variations is on
 *   offer when one it shows and can sell is; Offer records do not change the storefront price, so
 *   they do not count;
 * - limited quantity or out of stock, from the stock of what a buyer can see.
 *
 * `facts()` reads a product into plain values the catalog cache can hold. A discount window opens and
 * closes on its own, so the facts keep the price columns and `for()` decides on every read; the
 * stock badges change only when a product row is saved, which bumps the cache.
 */
class ProductBadges
{
    private const COLOR = '/^#[0-9A-Fa-f]{3,8}$/';

    public function __construct(private PricingService $pricing) {}

    /**
     * Expects the card relations (ProductRepository::cardRelations): labels with their translations,
     * active variations with their price columns and attribute values. A relation that is not loaded
     * counts as empty.
     *
     * @return array{labels: list<array{slug: string, name: string, text_color: ?string, background_color: ?string}>, is_new: bool, is_best_seller: bool, stock: ?string, variant_prices: ?list<array<string, mixed>>}
     *   variant_prices: the price columns of each variation a buyer can see and order; null without variations
     */
    public static function facts(Product $product): array
    {
        $labels = $product->relationLoaded('labels') ? $product->labels : new Collection;
        $variations = $product->relationLoaded('variations') ? $product->variations : new Collection;
        $visible = $variations->filter(fn (Product $variation) => ProductConfigurator::visible($variation));

        return [
            'labels' => $labels->map(fn (Label $label) => [
                'slug' => (string) $label->slug,
                'name' => (string) ($label->name ?: $label->translations->first()?->name),
                'text_color' => self::color($label->text_color),
                'background_color' => self::color($label->background_color),
            ])->filter(fn (array $label) => $label['name'] !== '')->values()->all(),
            'is_new' => (bool) $product->is_new,
            'is_best_seller' => (bool) $product->is_best_seller,
            'stock' => self::stock($product, $variations->isNotEmpty(), $visible),
            'variant_prices' => $variations->isEmpty() ? null : $visible
                ->filter(fn (Product $variation) => ProductConfigurator::available($variation))
                ->map(fn (Product $variation) => array_intersect_key($variation->getAttributes(), array_flip(PricingService::COLUMNS)))
                ->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts  from facts()
     * @param  Price  $price  the product's own displayed price
     * @return list<array{key: string, label: string, tone: string, text_color?: ?string, background_color?: ?string}>
     */
    public function for(array $facts, Price $price, ?CarbonInterface $at = null): array
    {
        $badges = [];

        foreach ($facts['labels'] ?? [] as $label) {
            $badges[] = [
                'key' => 'label:'.$label['slug'],
                'label' => $label['name'],
                'tone' => 'label',
                'text_color' => $label['text_color'],
                'background_color' => $label['background_color'],
            ];
        }

        if ($facts['is_new'] ?? false) {
            $badges[] = ['key' => 'new', 'label' => __('New'), 'tone' => 'accent'];
        }

        if ($facts['is_best_seller'] ?? false) {
            $badges[] = ['key' => 'best_seller', 'label' => __('Best seller'), 'tone' => 'ink'];
        }

        if ($this->onOffer($facts['variant_prices'] ?? null, $price, $at)) {
            $badges[] = ['key' => 'offer', 'label' => __('Offer'), 'tone' => 'sale'];
        }

        $badges[] = match ($facts['stock'] ?? null) {
            'limited' => ['key' => 'limited', 'label' => __('Limited quantity'), 'tone' => 'warning'],
            'out' => ['key' => 'out_of_stock', 'label' => __('Out of stock'), 'tone' => 'muted'],
            default => null,
        };

        return array_values(array_filter($badges));
    }

    /**
     * @param  ?list<array<string, mixed>>  $variantPrices
     */
    private function onOffer(?array $variantPrices, Price $price, ?CarbonInterface $at): bool
    {
        if ($variantPrices === null) {
            return $price->discounted();
        }

        foreach ($variantPrices as $columns) {
            if ($this->pricing->for((new Product)->setRawAttributes($columns), $at)->discounted()) {
                return true;
            }
        }

        return false;
    }

    /**
     * `out`, `limited` or null. A product with variations is judged by the active ones a buyer can see
     * (ProductConfigurator::visible): out of stock when none can be ordered; limited when every one
     * that can is counted (managed, no backorders) and together they hold 1 to the product's
     * low-stock threshold.
     *
     * @param  Collection<int, Product>  $variations  the visible ones
     */
    private static function stock(Product $product, bool $hasVariations, Collection $variations): ?string
    {
        $threshold = (int) $product->low_stock_threshold;

        // A set or bundle: what its components allow (BundleStock), limited at its own threshold.
        if ($product->bundle_type !== null) {
            $available = BundleStock::available($product);

            return match (true) {
                $available === 0 => 'out',
                $available !== null && $available <= $threshold => 'limited',
                default => null,
            };
        }

        if (! $hasVariations) {
            if (! ProductConfigurator::available($product)) {
                return 'out';
            }

            return self::counted($product) && (int) $product->stock_quantity <= $threshold ? 'limited' : null;
        }

        $available = $variations->filter(fn (Product $variation) => ProductConfigurator::available($variation));

        if ($available->isEmpty()) {
            return 'out';
        }

        if (! $available->every(fn (Product $variation) => self::counted($variation))) {
            return null;
        }

        return $available->sum(fn (Product $variation) => (int) $variation->stock_quantity) <= $threshold ? 'limited' : null;
    }

    /**
     * Stock that runs out: managed, without backorders, and above 0.
     */
    private static function counted(Product $item): bool
    {
        return $item->manage_stock && ! $item->allow_backorder && (int) $item->stock_quantity > 0;
    }

    private static function color(?string $value): ?string
    {
        return is_string($value) && preg_match(self::COLOR, $value) ? $value : null;
    }
}
