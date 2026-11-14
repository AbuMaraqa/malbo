<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\Admin\MarketingSettingsRequest;
use Modules\Store\Services\MarketingSettingsService;
use Modules\Store\Settings\MarketingSettings;

/**
 * Marketing settings: Meta Pixel and Conversions API, the catalog feed's address and token, cart
 * reminders. The token is never sent back to the browser, only whether one is saved.
 */
class MarketingSettingsController extends Controller
{
    public function __construct(private readonly MarketingSettingsService $settingsService) {}

    public function index(): Response
    {
        $settings = app(MarketingSettings::class);
        $token = $settings->feed_token ? ['token' => $settings->feed_token] : [];

        return Inertia::render('Store/Marketing/Index', [
            'settings' => [
                'pixel_enabled' => $settings->pixel_enabled,
                'meta_pixel_id' => $settings->meta_pixel_id,
                'capi_enabled' => $settings->capi_enabled,
                'has_capi_token' => filled($settings->meta_capi_token),
                'meta_test_event_code' => $settings->meta_test_event_code,
                'feed_token' => $settings->feed_token,
                'cart_reminders_enabled' => $settings->cart_reminders_enabled,
                'abandoned_after_minutes' => $settings->abandoned_after_minutes,
                'reminder_delays' => $settings->reminder_delays,
            ],
            'feed' => [
                'xml' => route('store.feeds.meta-catalog', $token),
                'csv' => route('store.feeds.meta-catalog-csv', $token),
            ],
            'limits' => ['min' => MarketingSettingsRequest::MIN_MINUTES, 'max' => MarketingSettingsRequest::MAX_MINUTES],
        ]);
    }

    public function update(MarketingSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->update($request->validated());

        return to_route('marketing.index')->with('success', __('Saved Successfully'));
    }
}
