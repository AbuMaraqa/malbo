<?php

namespace Modules\Store\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * How buyers reach a store (storefront footer, WhatsApp ordering).
 */
class StoreContactSettings extends Settings
{
    public ?string $phone;

    /** International number, digits only after normalising (e.g. 970599000000). */
    public ?string $whatsapp;

    public ?string $email;

    /** @var array<string, string> */
    public array $address;

    public ?string $facebook_url;

    public ?string $instagram_url;

    public ?string $tiktok_url;

    public ?string $x_url;

    public static function group(): string
    {
        return 'store_contact';
    }
}
