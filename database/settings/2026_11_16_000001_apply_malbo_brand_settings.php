<?php

use Illuminate\Support\Facades\Cache;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The MALBO identity (brand guidelines): maroon #6D1C2F and Readex Pro, for the storefront and the
 * admin panel. Only values still on the old defaults move; a color or font the owner chose stays.
 */
return new class extends SettingsMigration
{
    private const STORE = [['store_appearance.primary_color', '#1F6F50', '#6D1C2F'], ['store_appearance.font', 'tajawal', 'readex-pro']];

    private const PANEL = [['general.dashboard_color', '#1570EF', '#6D1C2F'], ['general.dashboard_font', 'tajawal', 'readex-pro']];

    public function up(): void
    {
        $this->move(fn (array $row) => [$row[0], $row[1], $row[2]]);
    }

    public function down(): void
    {
        $this->move(fn (array $row) => [$row[0], $row[2], $row[1]]);
    }

    private function move(Closure $direction): void
    {
        foreach ([...self::STORE, ...self::PANEL] as $row) {
            [$key, $from, $to] = $direction($row);
            $this->migrator->update($key, fn (string $value) => strcasecmp($value, $from) === 0 ? $to : $value);
        }

        // The storefront's theme is cached until the appearance changes (ThemeService).
        Cache::forget('store.theme.tokens');
    }
};
