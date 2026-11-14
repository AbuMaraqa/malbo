<?php

namespace Modules\Store\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Store-wide delivery rules. A zone's own free-delivery threshold wins over this one.
 */
class StoreShippingSettings extends Settings
{
    /** Free delivery when the order subtotal after discount reaches it; null for none. */
    public ?float $default_free_shipping_threshold;

    public static function group(): string
    {
        return 'store_shipping';
    }
}
