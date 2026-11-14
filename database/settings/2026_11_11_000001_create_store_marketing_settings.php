<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Marketing settings (Modules\Store\Settings\MarketingSettings): everything off until staff fill
 * in the Meta ids and switch it on. The Conversions API token is stored encrypted.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('store_marketing.pixel_enabled', false);
        $this->migrator->add('store_marketing.meta_pixel_id', null);
        $this->migrator->add('store_marketing.capi_enabled', false);
        $this->migrator->addEncrypted('store_marketing.meta_capi_token', null);
        $this->migrator->add('store_marketing.meta_test_event_code', null);
        $this->migrator->add('store_marketing.feed_token', null);
        $this->migrator->add('store_marketing.cart_reminders_enabled', false);
        $this->migrator->add('store_marketing.abandoned_after_minutes', 60);
        $this->migrator->add('store_marketing.reminder_delays', [60, 1440]);
    }

    public function down(): void
    {
        foreach (['pixel_enabled', 'meta_pixel_id', 'capi_enabled', 'meta_capi_token', 'meta_test_event_code', 'feed_token', 'cart_reminders_enabled', 'abandoned_after_minutes', 'reminder_delays'] as $name) {
            $this->migrator->deleteIfExists('store_marketing.'.$name);
        }
    }
};
