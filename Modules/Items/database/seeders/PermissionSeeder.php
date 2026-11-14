<?php

namespace Modules\Items\database\seeders;

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
                'name' => 'Category View List',
                'guard_name' => 'web',
                'module_name' => 'Category Management',
            ],
            [
                'name' => 'Category Create',
                'guard_name' => 'web',
                'module_name' => 'Category Management',
            ],
            [
                'name' => 'Category Update',
                'guard_name' => 'web',
                'module_name' => 'Category Management',
            ],
            [
                'name' => 'Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Category Management',
            ],
            [
                'name' => 'Category View',
                'guard_name' => 'web',
                'module_name' => 'Category Management',
            ],
            [
                'name' => 'Category Info',
                'guard_name' => 'web',
                'module_name' => 'Category Management',
            ],
            [
                'name' => 'Product View List',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],
            [
                'name' => 'Product Create',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],
            [
                'name' => 'Product Update',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],
            [
                'name' => 'Product Delete',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],
            [
                'name' => 'Product View',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],
            [
                'name' => 'Product Info',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],
            [
                'name' => 'Product Branch Pricings',
                'guard_name' => 'web',
                'module_name' => 'Product Management',
            ],


            [
                'name' => 'Attribute View List',
                'guard_name' => 'web',
                'module_name' => 'Attribute Management',
            ],
            [
                'name' => 'Attribute Create',
                'guard_name' => 'web',
                'module_name' => 'Attribute Management',
            ],
            [
                'name' => 'Attribute Update',
                'guard_name' => 'web',
                'module_name' => 'Attribute Management',
            ],
            [
                'name' => 'Attribute Delete',
                'guard_name' => 'web',
                'module_name' => 'Attribute Management',
            ],
            [
                'name' => 'Attribute View',
                'guard_name' => 'web',
                'module_name' => 'Attribute Management',
            ],
            [
                'name' => 'Attribute Info',
                'guard_name' => 'web',
                'module_name' => 'Attribute Management',
            ],


            [
                'name' => 'Tag View List',
                'guard_name' => 'web',
                'module_name' => 'Tag Management',
            ],
            [
                'name' => 'Tag Create',
                'guard_name' => 'web',
                'module_name' => 'Tag Management',
            ],
            [
                'name' => 'Tag Update',
                'guard_name' => 'web',
                'module_name' => 'Tag Management',
            ],
            [
                'name' => 'Tag Delete',
                'guard_name' => 'web',
                'module_name' => 'Tag Management',
            ],
            [
                'name' => 'Tag View',
                'guard_name' => 'web',
                'module_name' => 'Tag Management',
            ],
            [
                'name' => 'Tag Info',
                'guard_name' => 'web',
                'module_name' => 'Tag Management',
            ],


            [
                'name' => 'Label View List',
                'guard_name' => 'web',
                'module_name' => 'Label Management',
            ],
            [
                'name' => 'Label Create',
                'guard_name' => 'web',
                'module_name' => 'Label Management',
            ],
            [
                'name' => 'Label Update',
                'guard_name' => 'web',
                'module_name' => 'Label Management',
            ],
            [
                'name' => 'Label Delete',
                'guard_name' => 'web',
                'module_name' => 'Label Management',
            ],
            [
                'name' => 'Label View',
                'guard_name' => 'web',
                'module_name' => 'Label Management',
            ],
            [
                'name' => 'Label Info',
                'guard_name' => 'web',
                'module_name' => 'Label Management',
            ],

//            Todo Brand

            [
                'name' => 'Brand View List',
                'guard_name' => 'web',
                'module_name' => 'Brand Management',
            ],
            [
                'name' => 'Brand Create',
                'guard_name' => 'web',
                'module_name' => 'Brand Management',
            ],
            [
                'name' => 'Brand Update',
                'guard_name' => 'web',
                'module_name' => 'Brand Management',
            ],
            [
                'name' => 'Brand Delete',
                'guard_name' => 'web',
                'module_name' => 'Brand Management',
            ],
            [
                'name' => 'Brand View',
                'guard_name' => 'web',
                'module_name' => 'Brand Management',
            ],
            [
                'name' => 'Brand Info',
                'guard_name' => 'web',
                'module_name' => 'Brand Management',
            ],

            //            Todo Offer

            [
                'name' => 'Offer View List',
                'guard_name' => 'web',
                'module_name' => 'Offer Management',
            ],
            [
                'name' => 'Offer Create',
                'guard_name' => 'web',
                'module_name' => 'Offer Management',
            ],
            [
                'name' => 'Offer Update',
                'guard_name' => 'web',
                'module_name' => 'Offer Management',
            ],
            [
                'name' => 'Offer Delete',
                'guard_name' => 'web',
                'module_name' => 'Offer Management',
            ],
            [
                'name' => 'Offer View',
                'guard_name' => 'web',
                'module_name' => 'Offer Management',
            ],
            [
                'name' => 'Offer Info',
                'guard_name' => 'web',
                'module_name' => 'Offer Management',
            ],

            // Todo Addon
            [
                'name' => 'Addon View List',
                'guard_name' => 'web',
                'module_name' => 'Addon Management',
            ],
            [
                'name' => 'Addon Create',
                'guard_name' => 'web',
                'module_name' => 'Addon Management',
            ],
            [
                'name' => 'Addon Update',
                'guard_name' => 'web',
                'module_name' => 'Addon Management',
            ],
            [
                'name' => 'Addon Delete',
                'guard_name' => 'web',
                'module_name' => 'Addon Management',
            ],
            [
                'name' => 'Addon View',
                'guard_name' => 'web',
                'module_name' => 'Addon Management',
            ],
            [
                'name' => 'Addon Info',
                'guard_name' => 'web',
                'module_name' => 'Addon Management',
            ],


            // Todo Order
            [
                'name' => 'Order View List',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],
            [
                'name' => 'Order Create',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],
            [
                'name' => 'Order Update',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],
            [
                'name' => 'Order Delete',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],
            [
                'name' => 'Order View',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],
            [
                'name' => 'Order Info',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],
            // Printing an order's papers: the invoice and the waybill.
            [
                'name' => 'Order Invoice',
                'guard_name' => 'web',
                'module_name' => 'Order Management',
            ],

            // Shipments
            [
                'name' => 'Shipment View List',
                'guard_name' => 'web',
                'module_name' => 'Shipment Management',
            ],
            [
                'name' => 'Shipment Create',
                'guard_name' => 'web',
                'module_name' => 'Shipment Management',
            ],
            [
                'name' => 'Shipment Update',
                'guard_name' => 'web',
                'module_name' => 'Shipment Management',
            ],

            // Stock: the ledger and low stock (View List), receiving goods and adjustments (Update)
            [
                'name' => 'Stock View List',
                'guard_name' => 'web',
                'module_name' => 'Stock Management',
            ],
            [
                'name' => 'Stock Update',
                'guard_name' => 'web',
                'module_name' => 'Stock Management',
            ],

            // Returns and exchanges
            [
                'name' => 'Return Request View List',
                'guard_name' => 'web',
                'module_name' => 'Return Request Management',
            ],
            [
                'name' => 'Return Request Create',
                'guard_name' => 'web',
                'module_name' => 'Return Request Management',
            ],
            [
                'name' => 'Return Request Update',
                'guard_name' => 'web',
                'module_name' => 'Return Request Management',
            ],
            // Sets and bundle offers (phase 8B).
            ['name' => 'Bundle View List', 'guard_name' => 'web', 'module_name' => 'Bundle Management'],
            ['name' => 'Bundle Create', 'guard_name' => 'web', 'module_name' => 'Bundle Management'],
            ['name' => 'Bundle Update', 'guard_name' => 'web', 'module_name' => 'Bundle Management'],
            ['name' => 'Bundle Delete', 'guard_name' => 'web', 'module_name' => 'Bundle Management'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module_name' => $perm['module_name']]
            );
            $this->command->info("Permission created: {$perm['name']}");

            $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
            $role->givePermissionTo($perm['name']);
            $this->command->info("Permission {$perm['name']} assigned to role Super Admin");
        }
    }
}
