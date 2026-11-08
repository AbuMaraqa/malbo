<?php

namespace Modules\Store\Services;

use Modules\Items\Entities\Product;
use Modules\Items\Enums\AddonOptionStatus;
use Modules\Items\Enums\AddonType;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Support\Price;

/**
 * A storefront product's choices and what a choice costs: the attributes its variations vary
 * by, its addons with this product's allowed options and prices, stock and quantity limits.
 * Expects a product from ProductRepository::findPublishedBySlug (relations loaded).
 */
class ProductConfigurator
{
    /** Quantity cap when the product sets none. */
    public const MAX_QUANTITY = 20;

    public function __construct(
        private ProductRepositoryInterface $products,
        private PricingService $pricing,
    ) {}

    /**
     * @return array{
     *     attributes: array<int, array{id: int, name: string, swatch: bool, values: array<int, array{id: int, name: string, color: ?string}>}>,
     *     variants: array<int, array<int, int>>,
     *     addons: array<int, array{id: int, name: string, multiple: bool, required: bool, min: int, max: int, options: array<int, array{id: int, name: string, price: float, default: bool}>}>
     * }
     *   variants: variation id => [attribute id => value id]
     */
    public function options(Product $product): array
    {
        return [
            ...$this->variationChoices($product),
            'addons' => $this->addons($product),
        ];
    }

    /**
     * The variation whose values are exactly the selection, if any.
     *
     * @param  array<int, array<int, int>>  $variants
     * @param  array<int|string, mixed>  $selected  attribute id => value id
     */
    public function variantId(array $variants, array $selected): ?int
    {
        $selected = array_map('intval', array_filter($selected, fn ($value) => filled($value)));
        ksort($selected);

        foreach ($variants as $id => $values) {
            ksort($values);
            if ($values === $selected) {
                return $id;
            }
        }

        return null;
    }

    public function price(Product $item): Price
    {
        return $this->pricing->for($item);
    }

    public function inStock(Product $item): bool
    {
        if ($item->stock_status === 'out_of_stock') {
            return false;
        }

        return ! $item->manage_stock || $item->allow_backorder || (int) $item->stock_quantity > 0;
    }

    public function maxQuantity(Product $item): int
    {
        $max = (int) ($item->max_quantity_per_order ?: self::MAX_QUANTITY);

        if ($item->manage_stock && ! $item->allow_backorder) {
            $max = min($max, (int) $item->stock_quantity);
        }

        return max($max, 1);
    }

    private function variationChoices(Product $product): array
    {
        $attributes = [];
        $variants = [];

        foreach ($product->variations as $variation) {
            // A variation with a hidden value is not offered.
            if ($variation->attributeValues->contains(fn ($value) => ! $value->is_active)) {
                continue;
            }

            $values = [];

            foreach ($variation->attributeValues as $value) {
                $attribute = $value->attribute;
                if (! $attribute) {
                    continue;
                }

                $attributes[$attribute->id] ??= [
                    'id' => $attribute->id,
                    'name' => (string) $attribute->name,
                    'swatch' => (int) $attribute->type === AttributeType::COLOR->value,
                    'values' => [],
                    'sort' => [],
                ];
                $attributes[$attribute->id]['values'][$value->id] = ['id' => $value->id, 'name' => (string) $value->name, 'color' => $value->color_code];
                $attributes[$attribute->id]['sort'][$value->id] = [(int) $value->sort_order, $value->id];
                $values[$attribute->id] = $value->id;
            }

            if ($values !== []) {
                $variants[$variation->id] = $values;
            }
        }

        foreach ($attributes as &$attribute) {
            $sort = $attribute['sort'];
            uksort($attribute['values'], fn ($a, $b) => $sort[$a] <=> $sort[$b]);
            unset($attribute['sort']);
        }

        return ['attributes' => $attributes, 'variants' => $variants];
    }

    private function addons(Product $product): array
    {
        $overrides = $this->products->addonOptionOverrides($product->addons->pluck('pivot.id'));
        $addons = [];

        foreach ($product->addons as $addon) {
            // Rows for this product limit its options to them and may override their price.
            $rows = collect($overrides->get($addon->pivot->id, []))->keyBy('addon_option_id');

            $options = $addon->addonOptions
                ->filter(fn ($option) => $option->status === AddonOptionStatus::ACTIVE)
                ->when($rows->isNotEmpty(), fn ($options) => $options->whereIn('id', $rows->keys()->all()))
                ->mapWithKeys(fn ($option) => [$option->id => [
                    'id' => $option->id,
                    'name' => (string) $option->name,
                    'price' => (float) ($rows->get($option->id)?->price ?? $option->price),
                'default' => (bool) ($rows->get($option->id)?->is_default ?? $option->is_default),
                ]])
                ->all();

            if ($options === []) {
                continue;
            }

            $multiple = $addon->type === AddonType::CHECKBOX;
            $required = (bool) $addon->pivot->is_required;
            $max = $multiple ? min((int) ($addon->pivot->max_selection ?: count($options)), count($options)) : 1;

            $addons[$addon->id] = [
                'id' => $addon->id,
                'name' => (string) $addon->name,
                'multiple' => $multiple,
                'required' => $required,
                // Never more than can be chosen, or the product could not be ordered at all.
                'min' => $required ? min(max(1, (int) $addon->pivot->min_selection), $max) : 0,
                'max' => $max,
                'options' => $options,
            ];
        }

        return $addons;
    }
}
