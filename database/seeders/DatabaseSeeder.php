<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nwidart\Modules\Facades\Module;

/**
 * `php artisan db:seed` (or `migrate --seed`): every enabled module's `<Module>DatabaseSeeder`,
 * Core first (permissions, base data and the admin account from Modules/Core AdminSeeder).
 * A new module is seeded as soon as it has that class. Safe to run again.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(self::moduleSeeders());
    }

    /**
     * @return list<class-string<Seeder>>
     */
    public static function moduleSeeders(): array
    {
        $names = collect(Module::allEnabled())
            ->map(fn ($module) => $module->getName())
            // Core first: its PermissionSeeder resets the permissions table.
            ->sortBy(fn (string $name) => $name === 'Core' ? '' : $name)
            ->values();

        return $names
            ->map(fn (string $name) => "Modules\\{$name}\\database\\seeders\\{$name}DatabaseSeeder")
            ->filter(fn (string $class) => class_exists($class))
            ->values()
            ->all();
    }
}
