<?php

namespace Modules\Store\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Theme\FontCatalog;

class AppearanceRequest extends FormRequest
{
    /** Short texts, per locale. */
    public const LINES = ['announcement', 'hero_eyebrow', 'hero_title', 'story_title', 'membership_title'];

    /** Long texts, per locale. */
    public const PARAGRAPHS = ['hero_subtitle', 'story_text', 'membership_text'];

    public const IMAGES = ['logo', 'favicon', 'hero_image', 'story_image'];

    public const CONTACT = ['phone', 'whatsapp', 'email', 'facebook_url', 'instagram_url', 'tiktok_url', 'x_url'];

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font' => ['required', Rule::in(array_keys(FontCatalog::all()))],
            'values' => ['nullable', 'array', 'max:'.StoreAppearanceService::MAX_VALUES],
            'values.*.title.ar' => ['nullable', 'string', 'max:80'],
            'values.*.title.en' => ['nullable', 'string', 'max:80'],
            'values.*.text.ar' => ['nullable', 'string', 'max:300'],
            'values.*.text.en' => ['nullable', 'string', 'max:300'],
            'membership_enabled' => ['boolean'],
            'address' => ['nullable', 'array'],
            'address.ar' => ['nullable', 'string', 'max:160'],
            'address.en' => ['nullable', 'string', 'max:160'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'tiktok_url' => ['nullable', 'url:http,https', 'max:255'],
            'x_url' => ['nullable', 'url:http,https', 'max:255'],
            'removed_images' => ['nullable', 'array'],
            'removed_images.*' => [Rule::in(self::IMAGES)],
        ];

        foreach (self::LINES as $field) {
            $rules[$field] = ['nullable', 'array'];
            $rules["{$field}.ar"] = ['nullable', 'string', 'max:160'];
            $rules["{$field}.en"] = ['nullable', 'string', 'max:160'];
        }

        foreach (self::PARAGRAPHS as $field) {
            $rules[$field] = ['nullable', 'array'];
            $rules["{$field}.ar"] = ['nullable', 'string', 'max:1000'];
            $rules["{$field}.en"] = ['nullable', 'string', 'max:1000'];
        }

        // Raster images only: an SVG could run script on the store's own origin.
        foreach (self::IMAGES as $image) {
            $types = $image === 'favicon' ? 'png,jpg,jpeg,webp,gif,ico' : 'png,jpg,jpeg,webp,gif';
            $rules[$image] = ['nullable', 'file', 'max:'.StoreAppearanceService::MAX_IMAGE_KB, 'mimes:'.$types];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'primary_color' => __('Brand color'),
            'font' => __('Font'),
            'logo' => __('Logo'),
            'favicon' => __('Browser icon'),
            'hero_image' => __('Main image'),
            'story_image' => __('Story image'),
            'values' => __('Brand values'),
            'whatsapp' => __('WhatsApp number'),
            'phone' => __('Phone'),
            'email' => __('Email'),
        ];
    }
}
