<?php

namespace Modules\Items\Support;

use Illuminate\Support\Collection;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductActive;

/**
 * What a set or bundle can sell: it holds no stock, its components do. Pure rules over loaded
 * models (relations()); the storefront (ProductConfigurator, ProductBadges, the cart, the feed) asks
 * here, InventoryService still writes every unit.
 *
 * - A component offers its active variants a buyer can see (no hidden value) when its product has
 *   active variants, a pinned variant just that variant, else the product itself. The component's
 *   product (and a pinned variant's parent) must be active and not deleted; it need not be
 *   published: a set's trousers may never be sold alone.
 * - An item's units: 0 when marked out of stock; unlimited (null) when its stock is not managed or it
 *   allows backorders; else its stock (never below 0).
 * - A bundle can sell the least, over its components, of floor(units of the component's best choice /
 *   its quantity). No components, or one that offers nothing: none.
 * - A chosen configuration counts each variant once over every component that took it.
 */
final class BundleStock
{
    /**
     * What a bundle needs loaded. $full adds what a page shows (names, images, values).
     *
     * @return array<int|string, mixed>
     */
    public static function relations(bool $full = false, string $prefix = 'bundleComponents'): array
    {
        $active = fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value)->orderBy('id');

        $relations = [
            $prefix,
            $prefix.'.product' => fn ($product) => $product->withTrashed(),
            $prefix.'.product.parent' => fn ($parent) => $parent->withTrashed(),
            $prefix.'.product.attributeValues',
            $prefix.'.product.variations' => $active,
            $prefix.'.product.variations.attributeValues',
        ];

        if ($full) {
            $relations = [
                ...$relations,
                $prefix.'.product.translations',
                $prefix.'.product.media',
                $prefix.'.product.parent.translations',
                $prefix.'.product.parent.media',
                $prefix.'.product.attributeValues.translations',
                $prefix.'.product.attributeValues.attribute.translations',
                $prefix.'.product.variations.translations',
                $prefix.'.product.variations.media',
                $prefix.'.product.variations.attributeValues.translations',
                $prefix.'.product.variations.attributeValues.attribute.translations',
            ];
        }

        return $relations;
    }

    /**
     * What the buyer may pick for this component (empty: it cannot be sold now or ever).
     *
     * @return Collection<int, Product>
     */
    public static function choices(BundleComponent $component): Collection
    {
        $product = $component->product;

        if ($product === null || $product->trashed() || ! self::active($product)) {
            return collect();
        }

        if ($product->parent_id) {
            $parent = $product->parent;

            return $parent !== null && ! $parent->trashed() && self::active($parent) && self::visible($product) ? collect([$product]) : collect();
        }

        if ($product->variations->isNotEmpty()) {
            return $product->variations->filter(fn (Product $variation) => self::visible($variation))->values();
        }

        return collect([$product]);
    }

    /**
     * Whether the buyer chooses a variant for this component (its product has active variants).
     */
    public static function choosable(BundleComponent $component): bool
    {
        $product = $component->product;

        return $product !== null && ! $product->parent_id && $product->variations->isNotEmpty();
    }

    /**
     * Units of one item a sale may take now: null is unlimited.
     */
    public static function units(Product $item): ?int
    {
        if ($item->stock_status === 'out_of_stock') {
            return 0;
        }

        if (! $item->manage_stock || $item->allow_backorder) {
            return null;
        }

        return max(0, (int) $item->stock_quantity);
    }

    /** Choice combinations of one product family worked out exactly; above it, a greedy bound. */
    private const EXACT_COMBINATIONS = 4096;

    /**
     * How many of this bundle can be sold now, whatever the buyer picks best: null is unlimited.
     *
     * Components of one product family (the same product, its variants, a pinned variant of it) draw
     * on the same stock, so they are judged together: the best assignment of their choices, found
     * by trying every combination (at most EXACT_COMBINATIONS), else by a greedy assignment that is
     * always possible (never above what a buyer can order; it may be below on huge families).
     * Families never share stock, so the bundle can sell the least of them.
     */
    public static function available(Product $bundle): ?int
    {
        $components = $bundle->bundleComponents;

        if ($components->isEmpty()) {
            return 0;
        }

        $families = [];

        foreach ($components as $component) {
            $choices = self::choices($component);

            if ($choices->isEmpty()) {
                return 0;
            }

            $product = $component->product;
            $families[(int) ($product->parent_id ?: $product->id)][] = ['choices' => $choices->values()->all(), 'quantity' => max(1, (int) $component->quantity)];
        }

        $least = null;

        foreach ($families as $pieces) {
            $bound = self::familyBound($pieces);

            if ($bound !== null) {
                $least = $least === null ? $bound : min($least, $bound);
            }
        }

        return $least;
    }

    /**
     * @param  list<array{choices: list<Product>, quantity: int}>  $pieces
     */
    private static function familyBound(array $pieces): ?int
    {
        $combinations = 1;

        foreach ($pieces as $piece) {
            $combinations *= count($piece['choices']);

            if ($combinations > self::EXACT_COMBINATIONS) {
                return self::greedyBound($pieces);
            }
        }

        return self::bestAssignment($pieces, 0, [], []);
    }

    /**
     * The most bundles any assignment of the pieces' choices allows (null: unlimited).
     *
     * @param  list<array{choices: list<Product>, quantity: int}>  $pieces
     * @param  array<int, int>  $needed  item id => pieces per bundle so far
     * @param  array<int, Product>  $items
     */
    private static function bestAssignment(array $pieces, int $index, array $needed, array $items): ?int
    {
        if ($index === count($pieces)) {
            return self::boundOf($needed, $items);
        }

        $best = 0;

        foreach ($pieces[$index]['choices'] as $item) {
            $next = $needed;
            $next[$item->id] = ($next[$item->id] ?? 0) + $pieces[$index]['quantity'];
            $bound = self::bestAssignment($pieces, $index + 1, $next, $items + [$item->id => $item]);

            if ($bound === null) {
                return null;
            }

            $best = max($best, $bound);
        }

        return $best;
    }

    /**
     * A bundle count that an actual assignment reaches: the largest n (binary search up to the
     * pooled bound) for which giving each piece, largest first, the choice with the most stock left
     * fits n bundles.
     *
     * @param  list<array{choices: list<Product>, quantity: int}>  $pieces
     */
    private static function greedyBound(array $pieces): ?int
    {
        $pool = [];
        $quantity = 0;

        foreach ($pieces as $piece) {
            $quantity += $piece['quantity'];

            foreach ($piece['choices'] as $item) {
                $pool[$item->id] = self::units($item);
            }
        }

        if (in_array(null, $pool, true)) {
            // An unlimited choice: every piece that offers one takes it; the rest share the stock.
            $limited = array_values(array_filter($pieces, fn (array $piece) => ! collect($piece['choices'])->contains(fn (Product $item) => self::units($item) === null)));

            return $limited === [] ? null : self::greedyBound($limited);
        }

        usort($pieces, fn (array $a, array $b) => $b['quantity'] <=> $a['quantity']);
        $low = 0;
        $high = intdiv((int) array_sum($pool), max(1, $quantity));

        while ($low < $high) {
            $try = intdiv($low + $high + 1, 2);
            $left = $pool;
            $fits = true;

            foreach ($pieces as $piece) {
                $best = collect($piece['choices'])->sortByDesc(fn (Product $item) => $left[$item->id])->first();

                if ($left[$best->id] < $try * $piece['quantity']) {
                    $fits = false;

                    break;
                }

                $left[$best->id] -= $try * $piece['quantity'];
            }

            $fits ? $low = $try : $high = $try - 1;
        }

        return $low;
    }

    /**
     * @param  array<int, int>  $needed  item id => pieces per bundle
     * @param  array<int, Product>  $items
     */
    private static function boundOf(array $needed, array $items): ?int
    {
        $least = null;

        foreach ($needed as $id => $count) {
            $units = self::units($items[$id]);

            if ($units !== null) {
                $bundles = intdiv($units, $count);
                $least = $least === null ? $bundles : min($least, $bundles);
            }
        }

        return $least;
    }

    /**
     * Why this item cannot be given as a piece of a set now (an exchange of a set's piece), or null.
     * The same rules as a component's choices: active and not deleted, its parent too, no hidden
     * value, not a product whose variants are sold instead, not a set; and the stock (units()). It
     * need not be published: a set's piece may never be sold alone. Needs its parent, values and
     * active variants loaded (ProductRepository::forCart).
     */
    public static function pieceProblem(?Product $candidate, int $quantity): ?string
    {
        $parent = $candidate?->parent;

        if ($candidate === null || $candidate->trashed() || $candidate->bundle_type !== null || ! self::active($candidate)
            || ($candidate->parent_id && ($parent === null || $parent->trashed() || ! self::active($parent)))
            || ! self::visible($candidate) || (! $candidate->parent_id && $candidate->variations->isNotEmpty())) {
            return __('This product is no longer available.');
        }

        $units = self::units($candidate);

        if ($units === 0) {
            return __('Currently unavailable');
        }

        return $units !== null && $quantity > $units ? __('Only :count left. Lower the quantity.', ['count' => $units]) : null;
    }

    public static function orderable(Product $bundle): bool
    {
        return self::available($bundle) !== 0;
    }

    /**
     * The buyer's choices (component id => product id) checked against the bundle: a component whose
     * only choice is fixed (a product without variants, a pinned variant) is chosen by itself.
     *
     * @param  array<int|string, mixed>  $choices
     * @return array{chosen: array<int, Product>, missing: list<BundleComponent>, invalid: list<BundleComponent>}
     *   chosen: component id => the item sold
     */
    public static function resolve(Product $bundle, array $choices): array
    {
        $chosen = [];
        $missing = [];
        $invalid = [];

        foreach ($bundle->bundleComponents as $component) {
            $options = self::choices($component);

            if ($options->isEmpty()) {
                $invalid[] = $component;

                continue;
            }

            if (! self::choosable($component)) {
                $chosen[(int) $component->id] = $options->first();

                continue;
            }

            $id = $choices[$component->id] ?? null;

            if (! is_numeric($id)) {
                $missing[] = $component;

                continue;
            }

            $item = $options->firstWhere('id', (int) $id);

            if ($item === null) {
                $invalid[] = $component;

                continue;
            }

            $chosen[(int) $component->id] = $item;
        }

        return ['chosen' => $chosen, 'missing' => $missing, 'invalid' => $invalid];
    }

    /**
     * How many of this bundle the chosen items allow (resolve()'s `chosen`): null is unlimited.
     *
     * @param  array<int, Product>  $chosen  component id => item
     */
    public static function maxFor(Product $bundle, array $chosen): ?int
    {
        $quantities = $bundle->bundleComponents->mapWithKeys(fn (BundleComponent $component) => [(int) $component->id => (int) $component->quantity])->all();
        $needed = [];
        $items = [];

        foreach ($chosen as $componentId => $item) {
            $needed[$item->id] = ($needed[$item->id] ?? 0) + max(1, $quantities[$componentId] ?? 1);
            $items[$item->id] = $item;
        }

        $least = null;

        foreach ($items as $id => $item) {
            $units = self::units($item);

            if ($units === null) {
                continue;
            }

            $bundles = intdiv($units, $needed[$id]);
            $least = $least === null ? $bundles : min($least, $bundles);
        }

        return $least;
    }

    /**
     * The regular price of one piece of a component, for a bundle priced as a percentage off its
     * components: the sale price when it is above 0 and below the base price, else the base price;
     * with variants, the lowest of the ones a buyer can pick. Dated discounts do not count.
     */
    public static function regularPrice(BundleComponent $component): float
    {
        $choices = self::choosable($component) ? self::choices($component) : collect([$component->product])->filter();

        return (float) ($choices->map(fn (Product $item) => self::listed($item))->min() ?? 0.0);
    }

    private static function listed(Product $item): float
    {
        $base = (float) $item->base_price;
        $sale = (float) $item->sale_price;

        return $sale > 0 && $sale < $base ? $sale : $base;
    }

    private static function active(Product $product): bool
    {
        return (int) $product->is_active === ProductActive::IS_ACTIVE->value;
    }

    private static function visible(Product $variation): bool
    {
        return ! $variation->attributeValues->contains(fn ($value) => ! $value->is_active);
    }
}
