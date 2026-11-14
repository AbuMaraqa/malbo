<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;

/**
 * The Users admin tells buyers from staff as EnsureStaff does: a merchant is a buyer, never staff.
 */
class MerchantsAreNotStaffTest extends WholesaleTestCase
{
    public function test_the_staff_only_filter_leaves_merchants_out(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
        $clerk = User::forceCreate(['name' => 'Clerk', 'email' => 'clerk@store.test', 'password' => 'x'])->assignRole(UserRole::USER->value);
        $buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@store.test', 'password' => 'x'])->assignRole(UserRole::CUSTOMER->value);
        $merchant = $this->merchant()->user;

        $ids = function (string $value) use ($admin): array {
            $ids = [];
            $this->actingAs($admin)->get($this->siteUrl('/ar/admin/users?perPage=100&filter[customers]='.$value))
                ->assertOk()
                ->assertInertia(function (Assert $page) use (&$ids) {
                    $ids = array_column($page->toArray()['props']['table']['data'], 'id');
                    $page->etc();
                });

            return $ids;
        };

        $staff = $ids('0');
        $this->assertContains($clerk->id, $staff);
        $this->assertNotContains($merchant->id, $staff);
        $this->assertNotContains($buyer->id, $staff);

        $buyers = $ids('1');
        $this->assertContains($buyer->id, $buyers);
        $this->assertContains($merchant->id, $buyers);
        $this->assertNotContains($clerk->id, $buyers);
    }
}
