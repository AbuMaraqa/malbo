<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Free delivery above this order value (after the discount) in zones that set no threshold of
 * their own. Null: no store-wide free delivery.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('store_shipping.default_free_shipping_threshold', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('store_shipping.default_free_shipping_threshold');
    }
};
