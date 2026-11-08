<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\database\seeders\AdminSeeder;

/**
 * `php artisan migrate --seed`: permissions and base data, then the admin account.
 * Safe to run again on an existing database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BaseDataSeeder::class,
            // Last: the Super Admin role has every permission by now.
            AdminSeeder::class,
        ]);
    }
}
