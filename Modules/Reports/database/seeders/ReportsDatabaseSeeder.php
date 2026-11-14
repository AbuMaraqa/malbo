<?php

namespace Modules\Reports\database\seeders;

use Illuminate\Database\Seeder;

class ReportsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
