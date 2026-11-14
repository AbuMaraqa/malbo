<?php

namespace Modules\Store\Services;

use Modules\Store\Settings\MarketingSettings;

/**
 * Saves the marketing settings. The Conversions API token is write-only: an empty field keeps the
 * saved token; `forget_capi_token` removes it, and the server events stop with it.
 */
class MarketingSettingsService
{
    /**
     * @param  array<string, mixed>  $data  MarketingSettingsRequest's fields
     */
    public function update(array $data): MarketingSettings
    {
        $settings = app(MarketingSettings::class);

        $settings->pixel_enabled = (bool) ($data['pixel_enabled'] ?? false);
        $settings->meta_pixel_id = filled($data['meta_pixel_id'] ?? null) ? (string) $data['meta_pixel_id'] : null;
        $settings->meta_test_event_code = filled($data['meta_test_event_code'] ?? null) ? (string) $data['meta_test_event_code'] : null;
        $settings->feed_token = filled($data['feed_token'] ?? null) ? (string) $data['feed_token'] : null;
        $settings->cart_reminders_enabled = (bool) ($data['cart_reminders_enabled'] ?? false);
        $settings->abandoned_after_minutes = (int) $data['abandoned_after_minutes'];
        $settings->reminder_delays = array_values(array_map('intval', $data['reminder_delays']));

        if (! empty($data['forget_capi_token'])) {
            $settings->meta_capi_token = null;
        } elseif (filled($data['meta_capi_token'] ?? null)) {
            $settings->meta_capi_token = trim((string) $data['meta_capi_token']);
        }

        $settings->capi_enabled = (bool) ($data['capi_enabled'] ?? false) && filled($settings->meta_capi_token);

        $settings->save();

        return $settings;
    }
}
