<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The home page's members section: on by default; empty texts fall back to the storefront's own wording.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $empty = ['ar' => '', 'en' => ''];

        $this->migrator->add('store_appearance.membership_enabled', true);
        $this->migrator->add('store_appearance.membership_title', $empty);
        $this->migrator->add('store_appearance.membership_text', $empty);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('store_appearance.membership_enabled');
        $this->migrator->deleteIfExists('store_appearance.membership_title');
        $this->migrator->deleteIfExists('store_appearance.membership_text');
    }
};
