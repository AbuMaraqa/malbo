<?php

namespace Tests\Feature\Store;

use Modules\Store\Settings\StoreAppearanceSettings;
use Tests\DatabaseTestCase;

/** database/settings/2026_11_18_000001_apply_malbo_store_font.php */
class MalboStoreFontTest extends DatabaseTestCase
{
    public function test_readex_pro_alone_moves_to_the_malbo_pair_and_back(): void
    {
        $this->setFont('readex-pro');

        $migration = $this->migration();
        $migration->up();
        $this->assertSame('malbo', $this->font());

        $migration->down();
        $this->assertSame('readex-pro', $this->font());
    }

    public function test_a_font_the_owner_chose_stays(): void
    {
        $this->setFont('cairo');

        $this->migration()->up();

        $this->assertSame('cairo', $this->font());
    }

    private function migration(): object
    {
        return require database_path('settings/2026_11_18_000001_apply_malbo_store_font.php');
    }

    private function setFont(string $font): void
    {
        $settings = app(StoreAppearanceSettings::class);
        $settings->font = $font;
        $settings->save();
    }

    private function font(): string
    {
        return app(StoreAppearanceSettings::class)->refresh()->font;
    }
}
