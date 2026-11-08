<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Settings\GeneralSettings;
use Modules\Core\Support\DashboardTheme;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Theme\ThemeService;
use Tests\DatabaseTestCase;

class DashboardThemeTest extends DatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'theme-admin@example.com', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'site_name' => 'Store',
            'email_address_for_contact' => 'store@example.com',
            'site_description' => 'A store',
            'dashboard_color' => '#1F6F50',
            'dashboard_font' => 'ibm-plex-sans-arabic',
            ...$overrides,
        ];
    }

    public function test_saving_changes_the_color_and_font_of_the_panel(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin/settings'))
            ->assertOk()
            ->assertSee('--color-brand-500:#1F6F50', false)
            ->assertSee('IBM Plex Sans Arabic', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.dashboard_color', '#1F6F50')
                ->where('settings.dashboard_font', 'ibm-plex-sans-arabic')
                ->where('dashboardTheme.css', fn (string $css) => str_contains($css, '--color-brand-500:#1F6F50')));
    }

    public function test_a_color_too_light_for_white_text_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['dashboard_color' => '#FFEE88']))
            ->assertSessionHasErrors('dashboard_color');
    }

    public function test_a_value_that_is_not_a_color_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['dashboard_color' => 'red;}body{display:none']))
            ->assertSessionHasErrors('dashboard_color');
    }

    public function test_only_the_offered_fonts_are_accepted(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['dashboard_font' => 'cairo']))
            ->assertSessionHasErrors('dashboard_font');
    }

    public function test_a_bad_stored_color_falls_back_to_the_default(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->dashboard_color = 'not-a-color';

        $this->assertStringContainsString('--color-brand-500:'.DashboardTheme::DEFAULT_COLOR, (new DashboardTheme($settings))->css());
    }

    public function test_the_panel_color_does_not_change_the_storefront_color(): void
    {
        $before = app(StoreAppearanceSettings::class)->primary_color;

        $this->actingAs($this->admin)->put($this->siteUrl('/ar/admin/settings'), $this->payload(['dashboard_color' => '#6B2D5C']));

        $this->assertSame($before, app(StoreAppearanceSettings::class)->primary_color);
        $this->assertStringNotContainsString('#6B2D5C', app(ThemeService::class)->css());
    }
}
