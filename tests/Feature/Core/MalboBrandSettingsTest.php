<?php

namespace Tests\Feature\Core;

use Modules\Core\Settings\GeneralSettings;
use Modules\Core\Support\DashboardTheme;
use Modules\Store\Settings\StoreAppearanceSettings;
use Tests\DatabaseTestCase;

/** database/settings/2026_11_16_000001_apply_malbo_brand_settings.php */
class MalboBrandSettingsTest extends DatabaseTestCase
{
    public function test_a_fresh_store_and_panel_wear_the_malbo_identity(): void
    {
        $this->assertSame('#6D1C2F', app(StoreAppearanceSettings::class)->primary_color);
        $this->assertSame('malbo', app(StoreAppearanceSettings::class)->font); // then 2026_11_18_000001_apply_malbo_store_font
        $this->assertSame('#6D1C2F', app(GeneralSettings::class)->dashboard_color);
        $this->assertSame('readex-pro', app(GeneralSettings::class)->dashboard_font);
        $this->assertSame('#6D1C2F', DashboardTheme::DEFAULT_COLOR);
    }

    public function test_only_the_old_defaults_move_and_down_brings_them_back(): void
    {
        $this->setStore('#1f6f50', 'cairo');
        $this->setPanel('#0F766E', 'tajawal');

        $migration = $this->migration();
        $migration->up();

        $this->assertSame(['#6D1C2F', 'cairo'], $this->store());
        $this->assertSame(['#0F766E', 'readex-pro'], $this->panel());

        $migration->down();

        $this->assertSame(['#1F6F50', 'cairo'], $this->store());
        $this->assertSame(['#0F766E', 'tajawal'], $this->panel());
    }

    private function migration(): object
    {
        return require database_path('settings/2026_11_16_000001_apply_malbo_brand_settings.php');
    }

    private function setStore(string $color, string $font): void
    {
        $settings = app(StoreAppearanceSettings::class);
        $settings->primary_color = $color;
        $settings->font = $font;
        $settings->save();
    }

    private function setPanel(string $color, string $font): void
    {
        $settings = app(GeneralSettings::class);
        $settings->dashboard_color = $color;
        $settings->dashboard_font = $font;
        $settings->save();
    }

    /** @return array{string, string} */
    private function store(): array
    {
        $settings = app(StoreAppearanceSettings::class)->refresh();

        return [$settings->primary_color, $settings->font];
    }

    /** @return array{string, string} */
    private function panel(): array
    {
        $settings = app(GeneralSettings::class)->refresh();

        return [$settings->dashboard_color, $settings->dashboard_font];
    }
}
