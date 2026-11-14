<?php

namespace Modules\Reports\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One permission per report (its CSV export included), all given to Super Admin. Safe to run again.
 */
class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'Sales Report View',
        'Channel Report View',
        'Product Report View',
        'Seller Report View',
        'Return Report View',
        'Merchant Report View',
        'Cart Report View',
        'Conversion Report View',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['module_name' => 'Reports']);
            $admin->givePermissionTo($name);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
