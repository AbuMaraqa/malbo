<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Spatie\Permission\Models\Role;

/**
 * The admin's merchants: the list with its filters, and a merchant's page (profile, document,
 * status with a note, tier, sales rep, minimum order).
 */
class MerchantPagesTest extends WholesaleTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_the_list_is_shaped_for_the_table(): void
    {
        $tier = $this->tier('ذهبي', 10);
        $merchant = $this->merchant(MerchantStatus::PENDING, ['tier_id' => $tier->id, 'sales_rep_id' => $this->admin->id, 'city_id' => $this->city('الخليل')->id]);

        $this->page('/merchants')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Merchant/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $merchant->id)
            ->where('table.data.0.shop_name', 'محل الأناقة')
            ->where('table.data.0.status', 'pending')
            ->where('table.data.0.tier', 'ذهبي')
            ->where('table.data.0.sales_rep', 'Admin')
            ->where('table.data.0.city', 'الخليل')
            ->missing('table.data.0.register_document_path')
            ->has('statuses', 4)
            ->etc());
    }

    public function test_each_filter(): void
    {
        $tier = $this->tier('ذهبي', 10);
        $city = $this->city('نابلس');
        $a = $this->merchant(MerchantStatus::PENDING, ['shop_name' => 'Alpha', 'tier_id' => $tier->id]);
        $b = $this->merchant(MerchantStatus::APPROVED, ['shop_name' => 'Beta', 'sales_rep_id' => $this->admin->id, 'city_id' => $city->id]);

        $only = fn (string $query, Merchant $merchant) => $this->page('/merchants?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $merchant->id)->etc());

        $only('filter[status]=pending', $a);
        $only('filter[tier_id]='.$tier->id, $a);
        $only('filter[tier_id]=none', $b);
        $only('filter[sales_rep_id]='.$this->admin->id, $b);
        $only('filter[city_id]='.$city->id, $b);
        $only('filter[search]=bet', $b);
    }

    public function test_the_edit_page(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('wholesale/registers/x.pdf', 'PDF');
        $merchant = $this->merchant(MerchantStatus::PENDING, ['register_document_path' => 'wholesale/registers/x.pdf', 'register_document_name' => 'cr.pdf']);

        $this->page('/merchants/'.$merchant->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Merchant/Edit')
            ->where('merchant.shop_name', 'محل الأناقة')
            ->where('status.value', 'pending')
            ->where('status.next.0.value', 'approved')
            ->where('status.next.1.value', 'rejected')
            ->where('status.next.1.needs_note', true)
            ->where('document.name', 'cr.pdf')
            ->where('document.url', fn (string $url) => str_contains($url, 'signature='))
            ->etc());
    }

    public function test_update_profile_tier_rep_and_minimum(): void
    {
        $tier = $this->tier('فضي', 5);
        $merchant = $this->merchant();

        $this->actingAs($this->admin)->put($this->url('/merchants/'.$merchant->id), $this->payload([
            'shop_name' => 'محل جديد',
            'tier_id' => $tier->id,
            'sales_rep_id' => $this->admin->id,
            'minimum_order_value' => 1500,
            'minimum_order_pieces' => 24,
        ]))->assertSessionHasNoErrors();

        $merchant->refresh();
        $this->assertSame('محل جديد', $merchant->shop_name);
        $this->assertSame($tier->id, $merchant->tier_id);
        $this->assertSame($this->admin->id, $merchant->sales_rep_id);
        $this->assertSame('1500.00', $merchant->minimum_order_value);
        $this->assertSame(24, $merchant->minimum_order_pieces);
    }

    public function test_the_sales_rep_must_be_staff(): void
    {
        $merchant = $this->merchant();
        $other = $this->merchant();

        $this->actingAs($this->admin)->put($this->url('/merchants/'.$merchant->id), $this->payload(['sales_rep_id' => $other->user_id]))
            ->assertSessionHasErrors('sales_rep_id');
        $this->assertNull($merchant->fresh()->sales_rep_id);
    }

    public function test_status_actions_from_the_page(): void
    {
        $merchant = $this->merchant(MerchantStatus::PENDING);

        $this->actingAs($this->admin)->put($this->url('/merchants/'.$merchant->id.'/status'), ['status' => 'rejected'])->assertSessionHasErrors('note');
        $this->actingAs($this->admin)->put($this->url('/merchants/'.$merchant->id.'/status'), ['status' => 'suspended', 'note' => 'x'])->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->put($this->url('/merchants/'.$merchant->id.'/status'), ['status' => 'approved', 'note' => 'مرحبا'])->assertSessionHasNoErrors();

        $this->assertSame(MerchantStatus::APPROVED, $merchant->fresh()->status);
    }

    public function test_the_document_needs_a_signed_link(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('wholesale/registers/x.pdf', 'PDF');
        $merchant = $this->merchant(MerchantStatus::PENDING, ['register_document_path' => 'wholesale/registers/x.pdf', 'register_document_name' => 'cr.pdf']);

        $this->actingAs($this->admin)->get($this->url('/merchants/'.$merchant->id.'/document'))->assertForbidden();

        $signed = URL::temporarySignedRoute('merchants.document', now()->addMinutes(10), ['merchant' => $merchant->id]);
        $this->actingAs($this->admin)->get($signed)->assertOk()->assertDownload('cr.pdf');

        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Product View');
        $this->actingAs($viewer)->get($signed)->assertForbidden();
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Merchant View List');
        $merchant = $this->merchant(MerchantStatus::PENDING);

        $this->actingAs($viewer)->get($this->url('/merchants'))->assertOk();
        $this->actingAs($viewer)->get($this->url('/merchants/'.$merchant->id.'/edit'))->assertOk();
        $this->actingAs($viewer)->put($this->url('/merchants/'.$merchant->id), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/merchants/'.$merchant->id.'/status'), ['status' => 'approved'])->assertForbidden();

        $nobody = User::forceCreate(['name' => 'N', 'email' => 'n-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Product View');
        $this->actingAs($nobody)->get($this->url('/merchants'))->assertForbidden();

        $this->assertSame(MerchantStatus::PENDING, $merchant->fresh()->status);
    }

    public function test_super_admin_has_the_permissions_and_the_sidebar_group(): void
    {
        $role = Role::findByName('Super Admin');

        foreach (['Merchant View List', 'Merchant Update', 'Wholesale Tier View List', 'Wholesale Tier Create', 'Wholesale Tier Update', 'Wholesale Tier Delete', 'Wholesale Price View List', 'Wholesale Price Update'] as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), $permission);
        }

        $this->actingAs($this->admin);
        $group = collect(app(SidebarService::class)->all())->firstWhere('title', 'Wholesale');
        $this->assertNotNull($group);
        $this->assertSame(['merchants.index', 'wholesale-tiers.index', 'wholesale-prices.index', 'wholesale-preorders.index'], array_column($group['children'], 'route'));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'shop_name' => 'محل الأناقة',
            'owner_name' => 'Owner',
            'city_id' => null,
            'phone' => '0599111222',
            'commercial_register' => 'CR-1001',
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

    private function page(string $path)
    {
        return $this->actingAs($this->admin)->get($this->url($path));
    }
}
