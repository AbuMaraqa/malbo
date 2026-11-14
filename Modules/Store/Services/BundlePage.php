<?php

namespace Modules\Store\Services;

use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Product;
use Modules\Items\Support\BundleStock;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Price;
use Modules\Store\Support\WhatsAppOrder;

/**
 * The page of a set or bundle offer: its components, each with the sizes and colours the buyer picks
 * from (a component of one item, or a fixed variant, has nothing to pick), and the buyer's choice
 * priced on the server. The price is the bundle's own (PricingService); what can be ordered comes
 * from the chosen variants' stock (BundleStock).
 *
 * Expects a bundle from ProductRepository::findPublishedBySlug (BundleStock::relations(full) loaded).
 */
class BundlePage
{
    public function __construct(
        private readonly ProductConfigurator $configurator,
        private readonly PricingService $pricing,
    ) {}

    /**
     * @return list<array{id: int, name: string, quantity: int, image: ?string, choosable: bool, fixed: ?string, available: bool, attributes: list<array<string, mixed>>, variants: list<array<string, mixed>>}>
     */
    public function components(Product $bundle): array
    {
        return $bundle->bundleComponents->map(function (BundleComponent $component): array {
            $product = $component->product;
            $choices = BundleStock::choices($component);
            $choosable = BundleStock::choosable($component);
            $attributes = [];
            $variants = [];

            if ($choosable) {
                $options = $this->configurator->variationChoices($product);
                $attributes = array_values(array_map(fn (array $attribute) => [
                    ...$attribute,
                    'values' => array_values(array_map(fn (array $value) => [
                        ...$value,
                        'color' => preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $value['color']) ? $value['color'] : null,
                    ], $attribute['values'])),
                ], $options['attributes']));
                $variants = $choices->filter(fn (Product $variant) => isset($options['variants'][$variant->id]))
                    ->map(fn (Product $variant) => [
                        'id' => $variant->id,
                        'values' => (object) $options['variants'][$variant->id],
                        'in_stock' => BundleStock::units($variant) !== 0,
                        'image' => $variant->getFirstMediaUrl('product') ?: null,
                    ])->values()->all();
            }

            $shown = $product?->parent ?? $product;

            return [
                'id' => (int) $component->id,
                'name' => self::componentName($component),
                'quantity' => (int) $component->quantity,
                'image' => $product ? ($product->getFirstMediaUrl('product') ?: $shown?->getFirstMediaUrl('product') ?: null) : null,
                'choosable' => $choosable,
                // A fixed variant's colour and size, as the buyer reads them.
                'fixed' => $product?->parent_id ? (self::values($product) ?: null) : null,
                'available' => $choices->contains(fn (Product $item) => BundleStock::units($item) !== 0),
                'attributes' => $attributes,
                'variants' => $variants,
            ];
        })->values()->all();
    }

    /**
     * @param  array{components?: mixed, quantity?: mixed}|null  $choice  from the URL
     * @return array<string, mixed>
     */
    public function quote(Product $bundle, ?array $choice = null): array
    {
        $chosenIds = self::cleanChoices($choice['components'] ?? []);
        $resolved = BundleStock::resolve($bundle, $chosenIds);
        $complete = $resolved['missing'] === [] && $resolved['invalid'] === [];
        $price = $this->pricing->for($bundle);

        $available = $complete ? BundleStock::maxFor($bundle, $resolved['chosen']) : BundleStock::available($bundle);
        $cap = (int) ($bundle->max_quantity_per_order ?: ProductConfigurator::MAX_QUANTITY);
        $maxQuantity = max(1, $available === null ? $cap : min($cap, $available));
        $quantity = is_numeric($choice['quantity'] ?? null) ? (int) $choice['quantity'] : 1;
        $quantity = max(1, min($quantity, $maxQuantity));
        $total = round($price->amount * $quantity, 2);

        $problems = [
            ...array_map(fn (BundleComponent $component) => __('Choose the size of :name', ['name' => self::componentName($component)]), $resolved['missing']),
            ...array_map(fn (BundleComponent $component) => __(':name is not available now.', ['name' => self::componentName($component)]), $resolved['invalid']),
        ];

        if ($complete && $available === 0) {
            $problems[] = __('Currently unavailable');
        }

        $number = app(StoreContactSettings::class)->whatsapp;
        $picked = [];

        foreach ($resolved['chosen'] as $componentId => $item) {
            $component = $bundle->bundleComponents->firstWhere('id', $componentId);

            if ($component !== null && BundleStock::choosable($component)) {
                $picked[$componentId] = (int) $item->id;
            }
        }

        return [
            // What the buyer picked for each component with a choice (component id => variant id).
            'components' => (object) $picked,
            'quantity' => $quantity,
            'max_quantity' => $maxQuantity,
            // No variant: Meta events name the bundle itself (CatalogIds).
            'variant_id' => null,
            'price' => $price->toArray(),
            'in_stock' => $complete ? $available !== 0 : BundleStock::orderable($bundle),
            'total' => $total,
            'total_label' => (new Price($total))->format(),
            'problems' => $problems,
            'ready' => $problems === [],
            'whatsapp_url' => filled($number) && $problems === []
                ? WhatsAppOrder::link($number, $this->orderText($bundle, $resolved['chosen'], $quantity, $total))
                : null,
        ];
    }

    /**
     * The component's product as the buyer reads it (a fixed variant: its product's name).
     */
    public static function componentName(BundleComponent $component): string
    {
        $product = $component->product;

        return (string) (($product?->parent ?? $product)?->name ?? '');
    }

    /**
     * A variant's colour, size... ("Black / M"); empty for a product without variants.
     */
    public static function values(Product $item): string
    {
        return $item->attributeValues->sortBy('attribute_id')->map(fn (AttributeValue $value) => (string) $value->name)->filter()->implode(' / ');
    }

    /**
     * Component id => product id, whole numbers only (a URL or a form can carry anything).
     *
     * @return array<int, int>
     */
    public static function cleanChoices(mixed $choices): array
    {
        $clean = [];

        foreach (is_array($choices) ? $choices : [] as $component => $product) {
            if (is_numeric($component) && is_numeric($product)) {
                $clean[(int) $component] = (int) $product;
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, Product>  $chosen
     */
    private function orderText(Product $bundle, array $chosen, int $quantity, float $total): string
    {
        $lines = [__('Hello :store, I would like to order:', ['store' => app(GeneralSettings::class)->site_name]), (string) $bundle->name];

        foreach ($bundle->bundleComponents as $component) {
            $item = $chosen[(int) $component->id] ?? null;
            $values = $item ? self::values($item) : '';
            $lines[] = '- '.self::componentName($component).((int) $component->quantity > 1 ? ' ×'.$component->quantity : '').($values !== '' ? ' — '.$values : '');
        }

        $lines[] = __('Quantity').': '.$quantity;
        $lines[] = __('Total').': '.(new Price($total))->format();
        $lines[] = route('store.product', ['slug' => $bundle->slug]);

        return implode("\n", $lines);
    }
}
