<?php

namespace Modules\Items\Enums;

/**
 * A product made of other products (`items_products.bundle_type`): a set is one piece of clothing
 * sold as such (a suit: shirt and trousers) at its own price; a bundle offer is separate products
 * sold together at an offer price. Both hold no stock: their components do.
 */
enum BundleType: string
{
    case SET = 'set';
    case BUNDLE = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::SET => __('Set'),
            self::BUNDLE => __('Bundle offer'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
