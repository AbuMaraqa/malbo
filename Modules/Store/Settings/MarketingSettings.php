<?php

namespace Modules\Store\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Marketing: Meta Pixel and Conversions API, the catalog feed's token, abandoned-cart reminders.
 * Edited on the admin's Marketing settings page (`Marketing Settings Update`).
 */
class MarketingSettings extends Settings
{
    /** The browser Pixel, loaded only after a visitor accepts marketing cookies. */
    public bool $pixel_enabled;

    public ?string $meta_pixel_id;

    /** Server events (Conversions API), the same events and ids as the Pixel. */
    public bool $capi_enabled;

    /** Encrypted at rest; never sent to a browser. */
    public ?string $meta_capi_token;

    /** Events Manager's "Test events" code, while testing. */
    public ?string $meta_test_event_code;

    /** When set, the catalog feed's URL must carry `?token=` with it. */
    public ?string $feed_token;

    public bool $cart_reminders_enabled;

    /** A cart with no activity for this long, with items and a contact, is abandoned. */
    public int $abandoned_after_minutes;

    /** @var list<int> minutes after the last activity at which each reminder is due (at most 2) */
    public array $reminder_delays;

    public static function group(): string
    {
        return 'store_marketing';
    }

    public static function encrypted(): array
    {
        return ['meta_capi_token'];
    }

    /**
     * Whether the server should post events: on, with a Pixel id and a token.
     */
    public function capiReady(): bool
    {
        return $this->capi_enabled && filled($this->meta_pixel_id) && filled($this->meta_capi_token);
    }

    public function pixelReady(): bool
    {
        return $this->pixel_enabled && filled($this->meta_pixel_id);
    }
}
