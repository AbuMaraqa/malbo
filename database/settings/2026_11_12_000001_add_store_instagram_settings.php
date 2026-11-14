<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The home page's Instagram section (phase 8B): off until staff add posts and switch it on; an empty
 * title falls back to the storefront's own wording.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('store_appearance.instagram_enabled', false);
        $this->migrator->add('store_appearance.instagram_title', []);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('store_appearance.instagram_enabled');
        $this->migrator->deleteIfExists('store_appearance.instagram_title');
    }
};
