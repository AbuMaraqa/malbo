<?php

namespace Modules\Store\Services;

use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\Product;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Price;
use Modules\Store\Support\WhatsAppOrder;

/**
 * The product page's data. `options()` is what the pickers show, with each variation's own price,
 * image and stock, so the browser can switch them without doing any math. `quote()` is a buyer's
 * choice (variation, addons, quantity) priced on the server: unit price, total, what still stops
 * the order, and the WhatsApp link. The page asks for a new quote whenever the choice changes.
 *
 * Expects a product from ProductRepository::findPublishedBySlug (relations loaded).
 */
class ProductPage
{
    public function __construct(private ProductConfigurator $configurator) {}

    /**
     * @return array{attributes: list<array<string, mixed>>, variants: list<array<string, mixed>>, addons: list<array<string, mixed>>}
     */
    public function options(Product $product): array
    {
        $options = $this->configurator->options($product);
        $variations = $product->variations->keyBy('id');

        return [
            'attributes' => array_values(array_map(fn (array $attribute) => [
                ...$attribute,
                'values' => array_values(array_map(fn (array $value) => [
                    ...$value,
                    'color' => preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $value['color']) ? $value['color'] : null,
                ], $attribute['values'])),
            ], $options['attributes'])),
            'variants' => array_map(function (int $id) use ($options, $variations) {
                $variation = $variations[$id];

                return [
                    'id' => $id,
                    'values' => (object) $options['variants'][$id],
                    'price' => $this->configurator->price($variation)->toArray(),
                    'image' => $variation->getFirstMediaUrl('product') ?: null,
                    'in_stock' => $this->configurator->inStock($variation),
                    'max_quantity' => $this->configurator->maxQuantity($variation),
                ];
            }, array_keys($options['variants'])),
            'addons' => array_values(array_map(fn (array $addon) => [
                ...$addon,
                'options' => array_values(array_map(fn (array $option) => [
                    ...$option,
                    'price_label' => $option['price'] > 0 ? (new Price($option['price']))->format() : null,
                ], $addon['options'])),
            ], $options['addons'])),
        ];
    }

    /**
     * @param  array{selected?: mixed, addons?: mixed, quantity?: mixed}|null  $choice  from the URL; null starts on the defaults
     * @return array<string, mixed>
     */
    public function quote(Product $product, ?array $choice = null): array
    {
        $options = $this->configurator->options($product);
        $choice ??= $this->defaults($product, $options);

        $selected = $this->cleanSelection($choice['selected'] ?? []);
        $addons = is_array($choice['addons'] ?? null) ? $choice['addons'] : [];

        $variantId = $this->configurator->variantId($options['variants'], $selected);
        $variant = $variantId === null ? null : $product->variations->firstWhere('id', $variantId);
        // What is being bought: the chosen variation, or the product itself when it has none.
        $item = $options['variants'] === [] ? $product : $variant;

        $price = $this->configurator->price($item ?? $product);
        $maxQuantity = $this->configurator->maxQuantity($item ?? $product);
        $quantity = is_numeric($choice['quantity'] ?? null) ? (int) $choice['quantity'] : 1;
        $quantity = max(1, min($quantity, $maxQuantity));

        $chosen = $this->chosenOptions($options['addons'], $addons);
        $total = round(($price->amount + collect($chosen)->flatten(1)->sum('price')) * $quantity, 2);
        $problems = $this->problems($options, $selected, $addons, $chosen, $variant, $item);

        $number = app(StoreContactSettings::class)->whatsapp;

        return [
            'selected' => (object) $selected,
            'addons' => (object) $addons,
            'quantity' => $quantity,
            'max_quantity' => $maxQuantity,
            'variant_id' => $variantId,
            'price' => $price->toArray(),
            // No complete choice yet: the lowest variation price, shown as "from".
            'price_from' => $item === null ? $this->lowestPrice($product, $options)?->format() : null,
            'in_stock' => $item === null || $this->configurator->inStock($item),
            'total' => $total,
            'total_label' => (new Price($total))->format(),
            'problems' => $problems,
            'whatsapp_url' => filled($number) && $problems === []
                ? WhatsAppOrder::link($number, $this->orderText($product, $options, $selected, $chosen, $quantity, $total))
                : null,
        ];
    }

    /**
     * The product's image first, then its gallery. A variation's image is sent with its variation.
     *
     * @return list<string>
     */
    public function images(Product $product): array
    {
        return collect([
            $product->getFirstMediaUrl('product'),
            ...$product->getMedia('product_gallery')->map->getUrl(),
        ])->filter()->unique()->values()->all();
    }

    /**
     * schema.org Product for search results: name, images, price and availability.
     *
     * @param  list<string>  $images
     * @return array<string, mixed>
     */
    public function structuredData(Product $product, array $images): array
    {
        $variations = $product->variations->whereIn('id', array_keys($this->configurator->options($product)['variants']));
        $items = $variations->isEmpty() ? collect([$product]) : $variations;
        $prices = $items->map(fn (Product $item) => $this->configurator->price($item)->amount);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $product->name,
            'description' => filled($product->short_description) ? (string) $product->short_description : null,
            'image' => $images ?: null,
            'sku' => $product->sku,
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'ILS',
                'lowPrice' => number_format((float) $prices->min(), 2, '.', ''),
                'highPrice' => number_format((float) $prices->max(), 2, '.', ''),
                'offerCount' => $items->count(),
                'availability' => $items->contains(fn (Product $item) => $this->configurator->inStock($item))
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('store.product', ['slug' => $product->slug]),
            ],
        ], fn ($value) => $value !== null);
    }

    /**
     * Start on the first variation in stock, else the first one. Options the admin marked as
     * default start chosen.
     *
     * @return array{selected: array<int, int>, addons: array<int, mixed>, quantity: int}
     */
    private function defaults(Product $product, array $options): array
    {
        $variations = $product->variations->keyBy('id');
        $first = collect($options['variants'])->keys()->first(fn (int $id) => $this->configurator->inStock($variations[$id])) ?? array_key_first($options['variants']);

        $addons = [];
        foreach ($options['addons'] as $addon) {
            $defaults = array_keys(array_filter($addon['options'], fn (array $option) => $option['default']));
            $addons[$addon['id']] = $addon['multiple'] ? array_slice($defaults, 0, $addon['max']) : ($defaults[0] ?? null);
        }

        return ['selected' => $first === null ? [] : $options['variants'][$first], 'addons' => $addons, 'quantity' => 1];
    }

    /**
     * A URL can carry anything: only attribute id => value id pairs of whole numbers count.
     *
     * @return array<int, int>
     */
    private function cleanSelection(mixed $selected): array
    {
        $clean = [];

        foreach (is_array($selected) ? $selected : [] as $attribute => $value) {
            if (is_numeric($attribute) && is_numeric($value)) {
                $clean[(int) $attribute] = (int) $value;
            }
        }

        return $clean;
    }

    /**
     * The chosen options this product really offers, per addon.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function chosenOptions(array $productAddons, array $addons): array
    {
        $chosen = [];

        foreach ($productAddons as $addon) {
            $ids = array_map('intval', array_filter(self::picked($addon, $addons), fn ($id) => is_numeric($id)));
            $chosen[$addon['id']] = array_values(array_intersect_key($addon['options'], array_flip($ids)));
        }

        return $chosen;
    }

    /**
     * The raw choice for an addon as a list; a checkbox group only counts when it is a list.
     */
    private static function picked(array $addon, array $addons): array
    {
        $value = $addons[$addon['id']] ?? null;

        if ($addon['multiple']) {
            return is_array($value) ? array_values(array_filter($value, 'filled')) : [];
        }

        return filled($value) && ! is_array($value) ? [$value] : [];
    }

    /**
     * What still stops the order, as messages for the buyer.
     *
     * @return list<string>
     */
    private function problems(array $options, array $selected, array $addons, array $chosen, ?Product $variant, ?Product $item): array
    {
        $problems = [];

        foreach ($options['attributes'] as $attribute) {
            if (blank($selected[$attribute['id']] ?? null)) {
                $problems[] = __('Choose :name', ['name' => $attribute['name']]);
            }
        }

        if ($problems === [] && $options['variants'] !== [] && $variant === null) {
            $problems[] = __('This combination is not available');
        }

        if ($item && ! $this->configurator->inStock($item)) {
            $problems[] = __('Currently unavailable');
        }

        foreach ($options['addons'] as $addon) {
            $count = count($chosen[$addon['id']] ?? []);

            if (count(self::picked($addon, $addons)) > $count) {
                $problems[] = __('Choose again from :name', ['name' => $addon['name']]);
            } elseif ($count < $addon['min']) {
                $problems[] = $addon['min'] > 1
                    ? __('Choose at least :min from :name', ['min' => $addon['min'], 'name' => $addon['name']])
                    : __('Choose :name', ['name' => $addon['name']]);
            } elseif ($count > $addon['max']) {
                $problems[] = __('Choose up to :max from :name', ['max' => $addon['max'], 'name' => $addon['name']]);
            }
        }

        return $problems;
    }

    private function orderText(Product $product, array $options, array $selected, array $chosen, int $quantity, float $total): string
    {
        $values = collect($options['attributes'])
            ->map(fn (array $attribute) => $attribute['values'][$selected[$attribute['id']] ?? 0]['name'] ?? null)
            ->filter()
            ->implode('، ');

        $lines = [
            __('Hello :store, I would like to order:', ['store' => app(GeneralSettings::class)->site_name]),
            $product->name.($values !== '' ? ' — '.$values : ''),
        ];

        foreach ($chosen as $addonId => $picked) {
            if ($picked !== []) {
                $lines[] = $options['addons'][$addonId]['name'].': '.collect($picked)->pluck('name')->implode('، ');
            }
        }

        $lines[] = __('Quantity').': '.$quantity;
        $lines[] = __('Total').': '.(new Price($total))->format();
        $lines[] = route('store.product', ['slug' => $product->slug]);

        return implode("\n", $lines);
    }

    private function lowestPrice(Product $product, array $options): ?Price
    {
        return $product->variations
            ->whereIn('id', array_keys($options['variants']))
            ->map(fn (Product $variation) => $this->configurator->price($variation))
            ->sortBy('amount')
            ->first();
    }
}
