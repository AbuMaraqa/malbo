<?php

namespace Modules\Core\database\seeders;

use Modules\Core\Entities\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        Role::findOrCreate(UserRole::SUPER_ADMIN->value);

        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Mohamad Maraqa',
                'password' => Hash::make('123456789'),
            ]
        );

        $user->assignRole(UserRole::SUPER_ADMIN->value);

        $user->generateAvatar();

        $roles = [
            UserRole::SUPER_ADMIN->value,
        ];

        foreach ($roles as $role){
            Role::findOrCreate($role);
        }
    }
}
