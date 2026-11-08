<?php

namespace Modules\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Support\DashboardTheme;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'email_address_for_contact' => ['required', 'email', 'max:255'],
            'site_description' => ['required', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_logo' => ['boolean'],

            'dashboard_color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) && ! DashboardTheme::isReadable($value)) {
                    $fail(__('This color is too light: white text on buttons would be hard to read.'));
                }
            }],
            'dashboard_font' => ['sometimes', 'required', Rule::in(DashboardTheme::FONTS)],

            'app_versions' => ['sometimes', 'array'],
            'app_versions.*.platform' => ['required', 'string', 'max:255', 'distinct'],
            'app_versions.*.store_url' => ['required', 'url:http,https', 'max:255'],
            'app_versions.*.min_version' => ['required', 'string', 'max:255'],
            'app_versions.*.latest_version' => ['required', 'string', 'max:255'],
            'app_versions.*.maintenance_mode' => ['boolean'],
            'app_versions.*.message' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_name' => __('Site Name'),
            'email_address_for_contact' => __('Email Address For Contact'),
            'site_description' => __('Site Description'),
            'logo' => __('Site Logo'),
            'dashboard_color' => __('Dashboard color'),
            'dashboard_font' => __('Dashboard font'),
            'app_versions.*.platform' => __('Platform (e.g., android, ios)'),
            'app_versions.*.store_url' => __('Store URL'),
            'app_versions.*.min_version' => __('Min Version'),
            'app_versions.*.latest_version' => __('Latest Version'),
            'app_versions.*.message' => __('Maintenance / Update Message'),
        ];
    }
}
