<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Tests\TenantTestCase;

/**
 * Store users and platform admins never authenticate on each other's side.
 */
class GuardSeparationTest extends TenantTestCase
{
    public function test_store_user_session_does_not_open_platform(): void
    {
        $user = User::forceCreate(['name' => 'Staff', 'email' => 'staff-'.Str::random(12).'@guards.test', 'password' => 'password'])
            ->assignRole('Super Admin');
        $this->actingAs($user, 'web');

        $this->asCentral();

        $this->get('http://localhost/ar/platform')->assertRedirect('http://localhost/ar/platform/login');
        $this->assertGuest('platform');
    }

    public function test_platform_admin_session_does_not_open_store_admin(): void
    {
        $admin = PlatformAdmin::create(['name' => 'P', 'email' => 'p-'.Str::random(12).'@guards.test', 'password' => 'secret-pass']);

        try {
            // Not actingAs(): it would also make `platform` the default guard for the store request.
            Auth::guard('platform')->setUser($admin);

            $this->get($this->tenantUrl('/ar/admin'))->assertRedirect($this->tenantUrl('/login'));
            $this->assertGuest('web');
        } finally {
            $admin->delete(); // central: not inside the tenant transaction
        }
    }
}
