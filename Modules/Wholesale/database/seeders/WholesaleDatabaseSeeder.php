<?php

namespace Modules\Wholesale\database\seeders;

use Illuminate\Database\Seeder;

class WholesaleDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
