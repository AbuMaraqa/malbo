<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Branch\database\seeders\BranchDatabaseSeeder;
use Modules\Core\database\seeders\AppVersionSeeder;
use Modules\Core\database\seeders\CurrencySeeder;
use Modules\Core\database\seeders\PermissionSeeder;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\database\seeders\GeoLocationDatabaseSeeder;
use Modules\Items\database\seeders\ItemsDatabaseSeeder;
use Modules\Store\database\seeders\StoreDatabaseSeeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds a freshly created store database. No user is created here:
 * the store owner comes from the sign-up data.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Core first: its PermissionSeeder resets the permissions table.
            PermissionSeeder::class,
            CurrencySeeder::class,
            AppVersionSeeder::class,
        ]);

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value);
        }

        $this->call([
            BranchDatabaseSeeder::class,
            ItemsDatabaseSeeder::class,
            GeoLocationDatabaseSeeder::class,
            StoreDatabaseSeeder::class,
        ]);
    }
}
