<?php

use Illuminate\Support\Facades\Cache;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The MALBO fonts (brand guidelines): KO Sans for Arabic and Readex Pro for Latin, the `malbo` pair
 * in config/store.php. Only a storefront still on Readex Pro alone moves; another font the owner
 * chose stays. The admin panel keeps its own font.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->move('readex-pro', 'malbo');
    }

    public function down(): void
    {
        $this->move('malbo', 'readex-pro');
    }

    private function move(string $from, string $to): void
    {
        $this->migrator->update('store_appearance.font', fn (string $value) => $value === $from ? $to : $value);

        // The storefront's theme is cached until the appearance changes (ThemeService).
        Cache::forget('store.theme.tokens');
    }
};
