<?php

namespace Modules\Store\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Role::query()->delete();
        // Permission::query()->delete();

        $permissions = [
            [
                'name' => 'Page View List',
                'guard_name' => 'web',
                'module_name' => 'Store Page Management',
            ],
            [
                'name' => 'Page Create',
                'guard_name' => 'web',
                'module_name' => 'Store Page Management',
            ],
            [
                'name' => 'Page Update',
                'guard_name' => 'web',
                'module_name' => 'Store Page Management',
            ],
            [
                'name' => 'Page Delete',
                'guard_name' => 'web',
                'module_name' => 'Store Page Management',
            ],
            [
                'name' => 'Contact Message View List',
                'guard_name' => 'web',
                'module_name' => 'Contact Message Management',
            ],
            [
                'name' => 'Contact Message View',
                'guard_name' => 'web',
                'module_name' => 'Contact Message Management',
            ],
            [
                'name' => 'Contact Message Update',
                'guard_name' => 'web',
                'module_name' => 'Contact Message Management',
            ],
            [
                'name' => 'Contact Message Delete',
                'guard_name' => 'web',
                'module_name' => 'Contact Message Management',
            ],
            [
                'name' => 'Member View List',
                'guard_name' => 'web',
                'module_name' => 'Member Management',
            ],
            [
                'name' => 'Member Delete',
                'guard_name' => 'web',
                'module_name' => 'Member Management',
            ],
            [
                'name' => 'Appearance Update',
                'guard_name' => 'web',
                'module_name' => 'Store Appearance Management',
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module_name' => $perm['module_name']]
            );
            $this->command?->info("Permission created: {$perm['name']}");

            $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
            $role->givePermissionTo($perm['name']);
            $this->command?->info("Permission {$perm['name']} assigned to role Super Admin");
        }
    }
}
