<?php

namespace Modules\Store\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Returns and exchanges from the storefront account.
 */
class StoreReturnSettings extends Settings
{
    /** Days after delivery a buyer may still ask to return or exchange items. Staff are not bound by it. */
    public int $return_window_days;

    public static function group(): string
    {
        return 'store_returns';
    }
}
