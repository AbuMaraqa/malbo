<?php

namespace Modules\Store\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * How a store's storefront looks and what its home page says. Texts are per locale: ['ar' => …, 'en' => …].
 */
class StoreAppearanceSettings extends Settings
{
    public string $primary_color;

    public string $font;

    public ?string $logo_path;

    public ?string $favicon_path;

    /** @var array<string, string> */
    public array $announcement;

    /** @var array<string, string> */
    public array $hero_eyebrow;

    /** @var array<string, string> */
    public array $hero_title;

    /** @var array<string, string> */
    public array $hero_subtitle;

    public ?string $hero_image_path;

    /** @var array<string, string> */
    public array $story_title;

    /** @var array<string, string> */
    public array $story_text;

    public ?string $story_image_path;

    // Up to 3 items of ['title' => ['ar' => …, 'en' => …], 'text' => [...]]. No @var: spatie/laravel-settings
    // parses property docblocks and does not understand array shapes.
    public array $values;

    /** The home page's members section (sign-ups land in the admin's Members list). */
    public bool $membership_enabled;

    /** @var array<string, string> */
    public array $membership_title;

    /** @var array<string, string> */
    public array $membership_text;

    public static function group(): string
    {
        return 'store_appearance';
    }
}
