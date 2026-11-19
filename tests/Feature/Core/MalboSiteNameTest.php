<?php

namespace Tests\Feature\Core;

use Modules\Core\Settings\GeneralSettings;
use Tests\DatabaseTestCase;

/** database/settings/2026_11_19_000001_apply_malbo_site_name.php */
class MalboSiteNameTest extends DatabaseTestCase
{
    public function test_a_fresh_store_is_called_malbo(): void
    {
        $this->assertSame('MALBO', app(GeneralSettings::class)->site_name);
        $this->assertStringContainsString('الجودة والراحة', app(GeneralSettings::class)->site_description);
    }

    public function test_the_template_name_moves_and_down_brings_it_back(): void
    {
        $this->setGeneral('Be Found Online', 'Be Found Online Description');

        $migration = $this->migration();
        $migration->up();
        $this->assertSame('MALBO', $this->general()[0]);

        $migration->down();
        $this->assertSame(['Be Found Online', 'Be Found Online'], $this->general());
    }

    public function test_a_name_the_owner_wrote_stays(): void
    {
        $this->setGeneral('My Shop', 'Shoes and more');

        $this->migration()->up();

        $this->assertSame(['My Shop', 'Shoes and more'], $this->general());
    }

    private function migration(): object
    {
        return require database_path('settings/2026_11_19_000001_apply_malbo_site_name.php');
    }

    private function setGeneral(string $name, string $description): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = $name;
        $settings->site_description = $description;
        $settings->save();
    }

    /** @return array{string, string} */
    private function general(): array
    {
        $settings = app(GeneralSettings::class)->refresh();

        return [$settings->site_name, $settings->site_description];
    }
}
