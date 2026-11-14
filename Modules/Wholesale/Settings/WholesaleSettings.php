<?php

namespace Modules\Wholesale\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Store-wide wholesale rules. A merchant's own minimum (wholesale_merchants) wins over these.
 */
class WholesaleSettings extends Settings
{
    /** The smallest order subtotal checkout accepts; null for none. */
    public ?float $minimum_order_value;

    /** The fewest pieces an order may hold; null for none. */
    public ?int $minimum_order_pieces;

    public static function group(): string
    {
        return 'wholesale';
    }
}
