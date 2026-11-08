<?php

namespace App\Tenancy;

use Nwidart\Modules\Facades\Module;

/**
 * Migration paths that build a store (tenant) database.
 *
 * `php artisan migrate` only runs the central `database/migrations` folder;
 * `php artisan tenants:migrate` runs these paths inside every store database.
 */
class TenantMigrationPaths
{
    /**
     * @return array<int, string> absolute paths
     */
    public static function all(): array
    {
        $paths = [
            database_path('migrations/tenant'),
            database_path('settings'),
        ];

        foreach (Module::allEnabled() as $module) {
            $paths[] = module_path($module->getName(), 'database/migrations');
        }

        return $paths;
    }
}
