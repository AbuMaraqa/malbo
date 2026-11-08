<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Http\Requests\Admin\SettingRequest;
use Modules\Core\Repositories\Contracts\SettingRepositoryInterface;
use Modules\Core\Services\SettingService;
use Modules\Core\Settings\GeneralSettings;
use Modules\Core\Support\DashboardTheme;
use Modules\Store\Theme\FontCatalog;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly SettingService $settingService,
    ) {}

    public function index(GeneralSettings $general): Response
    {
        $logo = $this->settings->logoHolder()->getFirstMedia('logo');

        return Inertia::render('Core/Settings/Index', [
            'settings' => [
                'site_name' => $general->site_name,
                'email_address_for_contact' => $general->email_address_for_contact,
                'site_description' => $general->site_description,
                'dashboard_color' => $general->dashboard_color,
                'dashboard_font' => $general->dashboard_font,
            ],
            'colorPresets' => ['#465FFF', ...array_values(config('store.color_presets'))],
            'fonts' => DashboardTheme::fontOptions(),
            // Both fonts, so each choice shows in its own letters.
            'fontPreviewUrl' => FontCatalog::googleFontsUrl(DashboardTheme::FONTS),
            'logo' => $logo ? [['id' => $logo->id, 'url' => $logo->getUrl()]] : [],
            'appVersions' => $this->settings->appVersions()->map(fn (AppVersion $version) => [
                'platform' => $version->platform,
                'store_url' => $version->store_url,
                'min_version' => $version->min_version,
                'latest_version' => $version->latest_version,
                'maintenance_mode' => $version->maintenance_mode,
                'message' => $version->message,
            ])->values(),
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $this->settingService->save($request->safe()->except('logo'), $request->file('logo'));

        return to_route('settings')->with('success', __('Saved Successfully'));
    }
}
