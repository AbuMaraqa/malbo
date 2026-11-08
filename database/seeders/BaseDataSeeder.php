<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Branch\database\seeders\BranchDatabaseSeeder;
use Modules\Core\database\seeders\CoreDatabaseSeeder;
use Modules\GeoLocation\database\seeders\GeoLocationDatabaseSeeder;
use Modules\Items\database\seeders\ItemsDatabaseSeeder;
use Modules\Store\database\seeders\StoreDatabaseSeeder;

/**
 * Permissions, roles and base data, without any user. The tests start from this.
 */
class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Core first: its PermissionSeeder resets the permissions table.
            CoreDatabaseSeeder::class,
            BranchDatabaseSeeder::class,
            ItemsDatabaseSeeder::class,
            GeoLocationDatabaseSeeder::class,
            StoreDatabaseSeeder::class,
        ]);
    }
}
