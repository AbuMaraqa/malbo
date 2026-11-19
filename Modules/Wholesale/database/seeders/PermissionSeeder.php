<?php

namespace Modules\Wholesale\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The wholesale admin's permissions, all given to Super Admin, and the Merchant role (no
 * permissions: merchants never reach the admin). Safe to run again.
 */
class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'Merchant View List',
        'Merchant Create',
        'Merchant Update',
        'Wholesale Tier View List',
        'Wholesale Tier Create',
        'Wholesale Tier Update',
        'Wholesale Tier Delete',
        'Wholesale Price View List',
        'Wholesale Price Update',
        'Wholesale Preorder View List',
        'Merchant Payment Create',
        'Merchant Credit Update',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => UserRole::MERCHANT->value, 'guard_name' => 'web']);

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['module_name' => 'Wholesale']);
            $admin->givePermissionTo($name);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
