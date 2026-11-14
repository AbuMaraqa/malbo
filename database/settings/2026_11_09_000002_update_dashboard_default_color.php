<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The new admin look is blue: a panel still on the old default color moves to the new one.
 * A color the store chose itself stays.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('general.dashboard_color', fn (string $color) => strtoupper($color) === '#465FFF' ? '#1570EF' : $color);
    }

    public function down(): void
    {
        $this->migrator->update('general.dashboard_color', fn (string $color) => strtoupper($color) === '#1570EF' ? '#465FFF' : $color);
    }
};
