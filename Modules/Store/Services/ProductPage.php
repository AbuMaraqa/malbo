<?php

namespace Modules\Store\Services;

use Modules\Core\Settings\GeneralSettings;
use Modules\Core\Support\RichText;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Price;
use Modules\Store\Support\WhatsAppOrder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
    public function __construct(
        private ProductConfigurator $configurator,
        private ProductBadges $badges,
        private CategoryRepositoryInterface $categories,
        private ProductRepositoryInterface $products,
    ) {}

    /** How far up the category tree the size guide is looked for: the tree can never loop forever. */
    private const MAX_CATEGORY_DEPTH = 10;

    /**
     * Whether the cart can hold this product. The cart carries no addons: a product that offers
     * some is ordered on WhatsApp (CartService refuses it too).
     */
    public function canAddToCart(Product $product): bool
    {
        return $product->addons->isEmpty();
    }

    /**
     * "Complete the look": the products staff put next to this one, each with its price and the
     * variants a buyer can add straight from here (their colour and size, and whether they can be
     * ordered). None set: an empty list (the page shows nothing; "You may also like" stays).
     *
     * @return list<array{id: int, name: string, url: string, image: ?string, price: array<string, mixed>, in_stock: bool, variants: list<array{id: int, label: string, in_stock: bool}>}>
     */
    public function look(Product $product): array
    {
        return $this->products->completeTheLook($product)->map(function (Product $item): array {
            // In the values' own order (S, M, L...), then as the variants were added.
            $variants = $item->variations->filter(fn (Product $variation) => ProductConfigurator::visible($variation) && $variation->attributeValues->isNotEmpty())
                ->sortBy([
                    fn (Product $a, Product $b) => $a->attributeValues->sortBy('attribute_id')->pluck('sort_order')->all() <=> $b->attributeValues->sortBy('attribute_id')->pluck('sort_order')->all(),
                    fn (Product $a, Product $b) => $a->id <=> $b->id,
                ])
                ->map(fn (Product $variation) => [
                    'id' => (int) $variation->id,
                    'label' => $variation->attributeValues->sortBy('attribute_id')->map(fn ($value) => (string) $value->name)->filter()->implode(' / '),
                    'in_stock' => ProductConfigurator::available($variation),
                ])->values()->all();

            return [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'url' => route('store.product', ['slug' => $item->slug]),
                'image' => $item->getFirstMediaUrl('product') ?: null,
                'price' => $this->configurator->price($item)->toArray(),
                'in_stock' => $item->variations->isEmpty() ? ProductConfigurator::available($item) : collect($variants)->contains('in_stock', true),
                'variants' => $variants,
            ];
        })->values()->all();
    }

    /**
     * The same badges as the product's card.
     *
     * @return list<array<string, mixed>>
     */
    public function badges(Product $product): array
    {
        return $this->badges->for(ProductBadges::facts($product), $this->configurator->price($product));
    }

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
            'variants' => array_map(function (int $id) use ($product, $options, $variations) {
                $variation = $variations[$id];

                return [
                    'id' => $id,
                    'values' => (object) $options['variants'][$id],
                    'price' => ($price = $this->configurator->price($variation))->toArray(),
                    // The offer badge follows the chosen variation's price.
                    'on_offer' => $price->discounted(),
                    'image' => $variation->getFirstMediaUrl('product') ?: null,
                    'in_stock' => $this->configurator->inStock($variation),
                    'max_quantity' => $this->configurator->maxQuantity($variation),
                    'details' => $this->details($product, $variation),
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
     * Fabric, composition, fit, care and the model in the photos, as label and text. A variation's
     * own value wins; what it leaves empty comes from the product. Fields with no value are left out.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function details(Product $product, ?Product $variation = null): array
    {
        $value = function (string $field) use ($product, $variation): string {
            $own = $variation ? trim((string) $variation->{$field}) : '';

            return $own !== '' ? $own : trim((string) $product->{$field});
        };

        $details = [];

        foreach (['fabric' => __('Fabric'), 'composition' => __('Composition'), 'fit' => __('Fit'), 'care' => __('Care')] as $key => $label) {
            if (($text = $value($key)) !== '') {
                $details[] = ['key' => $key, 'label' => $label, 'value' => $text];
            }
        }

        $model = array_filter([
            filled($height = $value('model_height')) ? __('Height :value cm', ['value' => $height]) : null,
            filled($weight = $value('model_weight')) ? __('Weight :value kg', ['value' => $weight]) : null,
            filled($size = $value('model_size')) ? __('Wears size :value', ['value' => $size]) : null,
        ]);

        if ($model !== []) {
            $details[] = ['key' => 'model', 'label' => __('The model'), 'value' => implode(' · ', $model)];
        }

        return $details;
    }

    /**
     * The size guide of the product's first category (in the order they were given) that has one;
     * when none has, the nearest parent category's, one level up at a time. Made safe again on the
     * way out. An inactive parent is walked through but its own guide is not shown.
     *
     * @return array{title: string, html: string}|null
     */
    public function sizeGuide(Product $product): ?array
    {
        $level = $product->categories->values();
        $seen = [];

        for ($depth = 0; $level->isNotEmpty() && $depth < self::MAX_CATEGORY_DEPTH; $depth++) {
            foreach ($level as $category) {
                $seen[$category->id] = true;
                $html = RichText::make((string) $category->size_guide)->toHtml();

                if ($category->status === CategoryStatus::ACTIVE && trim(strip_tags($html, '<img><table>')) !== '') {
                    return ['title' => (string) $category->name, 'html' => $html];
                }
            }

            $parentIds = $level->pluck('parent_id')->filter()->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => isset($seen[$id]))->unique()->values()->all();

            if ($parentIds === []) {
                break;
            }

            $parents = $this->categories->withTranslations($parentIds);
            $level = collect($parentIds)->map(fn (int $id) => $parents->get($id))->filter()->values();
        }

        return null;
    }

    /**
     * The product's image first, then its gallery, each with the color it shows (an attribute value
     * id) or null. A variation's image is sent with its variation.
     *
     * @return list<array{url: string, color: ?int}>
     */
    public function images(Product $product): array
    {
        $main = $product->getFirstMedia('product');

        return collect([
            ['url' => $main?->getUrl() ?? '', 'color' => null],
            ...$product->getMedia('product_gallery')->map(fn (Media $media) => [
                'url' => $media->getUrl(),
                'color' => is_numeric($color = $media->getCustomProperty('attribute_value_id')) ? (int) $color : null,
            ]),
        ])->filter(fn (array $image) => $image['url'] !== '')->unique('url')->values()->all();
    }

    /**
     * schema.org Product for search results: name, images, price and availability.
     *
     * With approved reviews, their average as `aggregateRating` ($rating: ProductReviews::summary).
     *
     * @param  list<string>  $images
     * @param  array{average: ?float, count: int}|null  $rating
     * @return array<string, mixed>
     */
    public function structuredData(Product $product, array $images, ?array $rating = null): array
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
            'aggregateRating' => ($rating['count'] ?? 0) > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $rating['average'], 1, '.', ''),
                'reviewCount' => (int) $rating['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
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
