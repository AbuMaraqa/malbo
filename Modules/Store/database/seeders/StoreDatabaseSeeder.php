<?php

namespace Modules\Store\database\seeders;

use Illuminate\Database\Seeder;

class StoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
