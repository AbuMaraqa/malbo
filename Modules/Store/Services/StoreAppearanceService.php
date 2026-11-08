<?php

namespace Modules\Store\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Theme\BrandColor;
use Modules\Store\Theme\FontCatalog;
use Modules\Store\Theme\ThemeService;

/**
 * Saves a store's appearance and contact settings. Images go to the store's public disk under
 * store/; a replaced or removed image's file is deleted. Clears the cached theme.
 */
class StoreAppearanceService
{
    /** upload key => settings property */
    private const IMAGES = [
        'logo' => 'logo_path',
        'favicon' => 'favicon_path',
        'hero_image' => 'hero_image_path',
        'story_image' => 'story_image_path',
    ];

    private const TEXTS = ['announcement', 'hero_eyebrow', 'hero_title', 'hero_subtitle', 'story_title', 'story_text', 'membership_title', 'membership_text'];

    private const CONTACT = ['phone', 'whatsapp', 'email', 'address', 'facebook_url', 'instagram_url', 'tiktok_url', 'x_url'];

    private const SOCIALS = ['facebook_url', 'instagram_url', 'tiktok_url', 'x_url'];

    public const MAX_VALUES = 3;

    public const MAX_IMAGE_KB = 4096;

    public function __construct(private ThemeService $theme) {}

    /**
     * @param  array<string, mixed>  $appearance  any of primary_color, font, the texts ({ar,en}) and values
     * @param  array<string, mixed>  $contact  any of the contact fields
     * @param  array<string, UploadedFile|false|null>  $uploads  logo, favicon, hero_image, story_image; false removes
     */
    public function save(array $appearance, array $contact, array $uploads): void
    {
        $settings = app(StoreAppearanceSettings::class);

        if (array_key_exists('primary_color', $appearance)) {
            if (! BrandColor::isValid((string) $appearance['primary_color'])) {
                throw new InvalidArgumentException('Invalid brand color.');
            }
            $settings->primary_color = BrandColor::fromHex($appearance['primary_color'])->hex();
        }

        if (array_key_exists('font', $appearance)) {
            if (! FontCatalog::has((string) $appearance['font'])) {
                throw new InvalidArgumentException('Unknown font.');
            }
            $settings->font = $appearance['font'];
        }

        foreach (self::TEXTS as $field) {
            if (array_key_exists($field, $appearance)) {
                $settings->{$field} = self::localized($appearance[$field]);
            }
        }

        if (array_key_exists('values', $appearance)) {
            $settings->values = self::values((array) $appearance['values']);
        }

        if (array_key_exists('membership_enabled', $appearance)) {
            $settings->membership_enabled = (bool) $appearance['membership_enabled'];
        }

        $this->validate($contact, $uploads);

        $replaced = [];
        $stored = [];
        foreach (self::IMAGES as $key => $property) {
            if (! array_key_exists($key, $uploads) || $uploads[$key] === null) {
                continue;
            }

            $replaced[] = $settings->{$property};
            $settings->{$property} = $uploads[$key] instanceof UploadedFile
                ? $stored[] = $uploads[$key]->store('store', 'public')
                : null;
        }

        try {
            DB::transaction(function () use ($settings, $contact) {
                $settings->save();
                $this->saveContact($contact);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);

            throw $e;
        }

        // Old files go only once the settings no longer point at them.
        Storage::disk('public')->delete(array_filter($replaced));
        $this->theme->forget();
    }

    /**
     * Raster images only (an SVG could run script on the store's own origin); web links only.
     *
     * @throws ValidationException
     */
    private function validate(array $contact, array $uploads): void
    {
        $rules = [];
        foreach (array_keys(self::IMAGES) as $key) {
            $rules[$key] = ['nullable', 'file', 'max:'.self::MAX_IMAGE_KB, 'mimes:'.($key === 'favicon' ? 'png,jpg,jpeg,webp,gif,ico' : 'png,jpg,jpeg,webp,gif')];
        }
        foreach (self::SOCIALS as $field) {
            $rules[$field] = ['nullable', 'url:http,https', 'max:255'];
        }

        $uploads = array_filter($uploads, fn ($upload) => $upload instanceof UploadedFile);
        Validator::make([...$uploads, ...array_intersect_key($contact, array_flip(self::SOCIALS))], $rules)->validate();
    }

    private function saveContact(array $contact): void
    {
        if ($contact === []) {
            return;
        }

        $settings = app(StoreContactSettings::class);

        foreach (self::CONTACT as $field) {
            if (! array_key_exists($field, $contact)) {
                continue;
            }

            $settings->{$field} = match ($field) {
                'address' => self::localized($contact[$field]),
                // wa.me wants the international number as digits only.
                'whatsapp' => filled($contact[$field]) ? preg_replace('/\D+/', '', (string) $contact[$field]) : null,
                default => filled($contact[$field]) ? trim((string) $contact[$field]) : null,
            };
        }

        $settings->save();
    }

    /**
     * @return array<string, string>
     */
    private static function localized(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return ['ar' => trim((string) ($value['ar'] ?? '')), 'en' => trim((string) ($value['en'] ?? ''))];
    }

    /**
     * Items without any title are dropped; at most MAX_VALUES are kept.
     */
    private static function values(array $items): array
    {
        $values = [];

        foreach ($items as $item) {
            $title = self::localized($item['title'] ?? []);
            if ($title['ar'] === '' && $title['en'] === '') {
                continue;
            }

            $values[] = ['title' => $title, 'text' => self::localized($item['text'] ?? [])];
        }

        return array_slice($values, 0, self::MAX_VALUES);
    }
}
