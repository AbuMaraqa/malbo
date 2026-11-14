<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Buyers may ask to return or exchange items for this many days after delivery.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('store_returns.return_window_days', 14);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('store_returns.return_window_days');
    }
};
