<?php

namespace Modules\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Core\Repositories\Contracts\SettingRepositoryInterface;
use Modules\Core\Settings\GeneralSettings;

class SettingService
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly GeneralSettings $general,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?UploadedFile $logo): void
    {
        DB::transaction(function () use ($data, $logo): void {
            $holder = $this->settings->logoHolder();

            if ($logo) {
                $holder->addMedia($logo)->toMediaCollection('logo', 'media');
            } elseif ($data['remove_logo'] ?? false) {
                $holder->clearMediaCollection('logo');
            }

            $this->general->site_name = $data['site_name'];
            $this->general->email_address_for_contact = $data['email_address_for_contact'];
            $this->general->site_description = $data['site_description'];
            $this->general->dashboard_color = strtoupper($data['dashboard_color'] ?? $this->general->dashboard_color);
            $this->general->dashboard_font = $data['dashboard_font'] ?? $this->general->dashboard_font;
            $this->general->site_logo_url = $this->settings->logoHolder()->getFirstMediaUrl('logo') ?: null;
            $this->general->save();

            if (array_key_exists('app_versions', $data)) {
                foreach ($data['app_versions'] as $version) {
                    $this->settings->saveAppVersion($version);
                }

                $this->settings->deleteAppVersionsExcept(array_column($data['app_versions'], 'platform'));
            }
        });
    }
}
