<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * Staff adding a merchant from the merchants list: its account (the password staff hand over), its
 * profile and terms, approved from the start with a line in its history.
 */
class MerchantAddTest extends WholesaleTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_the_add_page_has_the_choices_of_the_form(): void
    {
        $this->tier('ذهبي', 10);

        $this->actingAs($this->admin)->get($this->url('/merchants/add'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Merchant/Add')
            ->has('tiers', 1)
            ->has('salesReps')
            ->has('cities')
            ->has('storeMinimum'));
    }

    public function test_staff_add_an_approved_merchant_who_can_sign_in(): void
    {
        $tier = $this->tier('ذهبي', 10);

        $response = $this->actingAs($this->admin)->post($this->url('/merchants'), $this->payload([
            'email' => '  Shop@Example.TEST ',
            'tier_id' => $tier->id,
            'sales_rep_id' => $this->admin->id,
            'credit_enabled' => true,
            'credit_limit' => 5000,
        ]));

        $merchant = Merchant::query()->with('user', 'statusChanges')->sole();
        $response->assertRedirect($this->url('/merchants/'.$merchant->id.'/edit'))->assertSessionHasNoErrors();

        $this->assertSame(MerchantStatus::APPROVED, $merchant->status);
        $this->assertNotNull($merchant->approved_at);
        $this->assertSame('محل الأناقة', $merchant->shop_name);
        $this->assertSame('shop@example.test', $merchant->email);
        $this->assertSame($tier->id, $merchant->tier_id);
        $this->assertSame($this->admin->id, $merchant->sales_rep_id);
        $this->assertTrue((bool) $merchant->credit_enabled);

        // Its account: the Merchant role only, the email and the password staff typed.
        $this->assertSame('shop@example.test', $merchant->user->email);
        $this->assertSame('أحمد', $merchant->user->name);
        $this->assertTrue($merchant->user->hasRole(UserRole::MERCHANT->value));
        $this->assertSame(1, $merchant->user->roles()->count());
        $this->assertTrue(Hash::check('merchant-pass', $merchant->user->password));

        // The first line of its history says who approved it.
        $this->assertCount(1, $merchant->statusChanges);
        $this->assertNull($merchant->statusChanges[0]->from_status);
        $this->assertSame(MerchantStatus::APPROVED, $merchant->statusChanges[0]->to_status);
        $this->assertSame($this->admin->id, $merchant->statusChanges[0]->created_by);
    }

    public function test_an_email_with_an_account_is_a_form_error(): void
    {
        User::forceCreate(['name' => 'Customer', 'email' => 'taken@example.test', 'password' => 'secret-password']);

        $this->actingAs($this->admin)->post($this->url('/merchants'), $this->payload(['email' => 'TAKEN@example.test']))
            ->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post($this->url('/merchants'), $this->payload(['password' => 'short']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_credit_needs_its_own_permission(): void
    {
        $staff = User::forceCreate(['name' => 'S', 'email' => 's-'.Str::random(8).'@store.test', 'password' => 'secret-password'])
            ->givePermissionTo('Merchant View List', 'Merchant Create');

        $this->actingAs($staff)->post($this->url('/merchants'), $this->payload(['credit_enabled' => true, 'credit_limit' => 5000]))
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) Merchant::query()->sole()->credit_enabled);
    }

    public function test_staff_without_the_permission_cannot_add(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Merchant View List');

        $this->actingAs($viewer)->get($this->url('/merchants/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->url('/merchants'), $this->payload())->assertForbidden();

        $this->assertSame(0, Merchant::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'email' => 'shop-'.Str::random(6).'@example.test',
            'password' => 'merchant-pass',
            'shop_name' => 'محل الأناقة',
            'owner_name' => 'أحمد',
            'city_id' => null,
            'phone' => '0599000000',
            'commercial_register' => 'CR-123',
            'shop_address' => 'شارع',
            'tier_id' => null,
            'sales_rep_id' => null,
            'minimum_order_value' => null,
            'minimum_order_pieces' => null,
            ...$overrides,
        ];
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin/wholesale'.$path);
    }
}
