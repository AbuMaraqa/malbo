<?php

namespace Tests\Feature\Store;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Services\SidebarService;
use Modules\Store\Settings\MarketingSettings;

/**
 * The admin's marketing settings: Meta Pixel and Conversions API, the feed's token, cart reminders.
 * The Conversions API token goes in and never comes back out.
 */
class MarketingSettingsPageTest extends StorefrontTestCase
{
    public function test_staff_need_the_permission(): void
    {
        $this->actingAs($this->staff(['Page View List']))->get($this->siteUrl('/ar/admin/store/marketing'))->assertForbidden();
        $this->actingAs($this->staff(['Page View List']))->put($this->siteUrl('/ar/admin/store/marketing'), $this->payload())->assertForbidden();

        $this->actingAs($this->staff(['Marketing Settings Update']))->get($this->siteUrl('/ar/admin/store/marketing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Store/Marketing/Index')
                ->where('settings.pixel_enabled', false)
                ->where('settings.abandoned_after_minutes', 60)
                ->where('settings.reminder_delays', [60, 1440])
                ->where('feed.xml', route('store.feeds.meta-catalog'))
                ->etc());

        $titles = collect(app(SidebarService::class)->all())->pluck('title')->all();
        $this->assertContains('Marketing', $titles);
    }

    public function test_the_token_never_reaches_the_browser(): void
    {
        $settings = app(MarketingSettings::class);
        $settings->meta_capi_token = 'EAAB-very-secret';
        $settings->save();

        $response = $this->actingAs($this->staff(['Marketing Settings Update']))->get($this->siteUrl('/ar/admin/store/marketing'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('settings.has_capi_token', true)->missing('settings.meta_capi_token')->etc());
        $this->assertStringNotContainsString('EAAB-very-secret', $response->getContent());
    }

    public function test_saving_keeps_or_clears_the_token(): void
    {
        $staff = $this->staff(['Marketing Settings Update']);
        $url = $this->siteUrl('/ar/admin/store/marketing');

        $this->actingAs($staff)->from($url)->put($url, $this->payload(['meta_capi_token' => 'EAAB-first']))->assertRedirect($url)->assertSessionHasNoErrors();
        $settings = app(MarketingSettings::class)->refresh();
        $this->assertSame(['1234567890', 'EAAB-first', true, true], [$settings->meta_pixel_id, $settings->meta_capi_token, $settings->pixel_enabled, $settings->capi_enabled]);
        $this->assertSame([30, 720], $settings->reminder_delays);

        // An empty token field keeps the saved one.
        $this->actingAs($staff)->from($url)->put($url, $this->payload(['meta_capi_token' => '']))->assertSessionHasNoErrors();
        $this->assertSame('EAAB-first', app(MarketingSettings::class)->refresh()->meta_capi_token);

        // Removing it switches the server events off.
        $this->actingAs($staff)->from($url)->put($url, $this->payload(['forget_capi_token' => true, 'capi_enabled' => false]))->assertSessionHasNoErrors();
        $this->assertNull(app(MarketingSettings::class)->refresh()->meta_capi_token);
    }

    public function test_delays_are_validated(): void
    {
        $staff = $this->staff(['Marketing Settings Update']);
        $url = $this->siteUrl('/ar/admin/store/marketing');
        $put = fn (array $values) => $this->actingAs($staff)->from($url)->put($url, $this->payload($values));

        $put(['reminder_delays' => [60, 120, 240]])->assertSessionHasErrors('reminder_delays');
        $put(['reminder_delays' => [1440, 60]])->assertSessionHasErrors('reminder_delays.1');
        $put(['reminder_delays' => [5]])->assertSessionHasErrors('reminder_delays.0');
        $put(['meta_pixel_id' => 'abc'])->assertSessionHasErrors('meta_pixel_id');
        $put(['meta_pixel_id' => null])->assertSessionHasErrors('meta_pixel_id');
        $put(['capi_enabled' => true, 'meta_capi_token' => null])->assertSessionHasErrors('meta_capi_token');
        $put(['feed_token' => 'short'])->assertSessionHasErrors('feed_token');

        $this->assertNull(app(MarketingSettings::class)->refresh()->meta_pixel_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $values = []): array
    {
        return [
            'pixel_enabled' => true,
            'meta_pixel_id' => '1234567890',
            'capi_enabled' => true,
            'meta_capi_token' => 'EAAB-first',
            'forget_capi_token' => false,
            'meta_test_event_code' => null,
            'feed_token' => 'feed0123456789abcdef',
            'cart_reminders_enabled' => true,
            'abandoned_after_minutes' => 60,
            'reminder_delays' => [30, 720],
            ...$values,
        ];
    }
}
