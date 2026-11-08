<?php

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // First: it resets the permissions table, the other modules add theirs after it.
            PermissionSeeder::class,
            CurrencySeeder::class,
            AppVersionSeeder::class,
        ]);
    }
}
