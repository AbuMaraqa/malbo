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
            ...array_map(fn (string $action) => [
                'name' => 'Shipping Zone '.$action,
                'guard_name' => 'web',
                'module_name' => 'Shipping Zone Management',
            ], ['View List', 'Create', 'Update', 'Delete']),
            // Marketing (phase 8A).
            ['name' => 'Marketing Settings Update', 'guard_name' => 'web', 'module_name' => 'Marketing Management'],
            ['name' => 'Abandoned Cart View List', 'guard_name' => 'web', 'module_name' => 'Abandoned Cart Management'],
            ['name' => 'Stock Alert View List', 'guard_name' => 'web', 'module_name' => 'Stock Alert Management'],
            ['name' => 'Review View List', 'guard_name' => 'web', 'module_name' => 'Review Management'],
            ['name' => 'Review Update', 'guard_name' => 'web', 'module_name' => 'Review Management'],
            // Campaign pages and Instagram content (phase 8B).
            ['name' => 'Campaign Page View List', 'guard_name' => 'web', 'module_name' => 'Campaign Page Management'],
            ['name' => 'Campaign Page Create', 'guard_name' => 'web', 'module_name' => 'Campaign Page Management'],
            ['name' => 'Campaign Page Update', 'guard_name' => 'web', 'module_name' => 'Campaign Page Management'],
            ['name' => 'Campaign Page Delete', 'guard_name' => 'web', 'module_name' => 'Campaign Page Management'],
            ['name' => 'Instagram Post View List', 'guard_name' => 'web', 'module_name' => 'Instagram Management'],
            ['name' => 'Instagram Post Create', 'guard_name' => 'web', 'module_name' => 'Instagram Management'],
            ['name' => 'Instagram Post Update', 'guard_name' => 'web', 'module_name' => 'Instagram Management'],
            ['name' => 'Instagram Post Delete', 'guard_name' => 'web', 'module_name' => 'Instagram Management'],
            // The home page slider.
            ['name' => 'Hero Slide View List', 'guard_name' => 'web', 'module_name' => 'Hero Slide Management'],
            ['name' => 'Hero Slide Create', 'guard_name' => 'web', 'module_name' => 'Hero Slide Management'],
            ['name' => 'Hero Slide Update', 'guard_name' => 'web', 'module_name' => 'Hero Slide Management'],
            ['name' => 'Hero Slide Delete', 'guard_name' => 'web', 'module_name' => 'Hero Slide Management'],
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
