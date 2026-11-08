<?php

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The admin panel's first account: a Super Admin (every permission, given to the role by the
 * modules' PermissionSeeders). Set ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in .env (config/admin.php).
 * Run again to reset that account's name and password.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // Login lowers its input and PostgreSQL compares exactly: a capital here could never sign in.
        $email = Str::lower(trim((string) config('admin.email')));

        $user = User::query()->updateOrCreate(['email' => $email], [
            'name' => config('admin.name'),
            'password' => Hash::make((string) config('admin.password')),
        ]);
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        $user->syncRoles([UserRole::SUPER_ADMIN->value]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info("Admin account: {$email}");
    }
}
