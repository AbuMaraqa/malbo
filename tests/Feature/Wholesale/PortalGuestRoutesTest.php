<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Tests\DatabaseTestCase;

/**
 * The 6B portal pages as a real guest request meets them: no `URL::defaults` set by the test (the
 * storefront test case sets them, which would hide a route built without its locale before
 * SetRouteLocale runs). Guests are sent to sign in; the shared catalog link needs no login.
 */
class PortalGuestRoutesTest extends DatabaseTestCase
{
    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach (['/ar/wholesale/catalog-pdf', '/ar/wholesale/catalog-pdf/download', '/ar/wholesale/account/statement', '/ar/wholesale/account/statement/pdf', '/en/wholesale/account/statement'] as $path) {
            $locale = substr($path, 1, 2);
            $this->get($this->siteUrl($path))->assertRedirect($this->siteUrl('/'.$locale.'/account/login'));
        }

        $this->post($this->siteUrl('/he/wholesale/catalog-pdf/shares'))->assertRedirect($this->siteUrl('/he/account/login'));
        $this->post($this->siteUrl('/ar/wholesale/orders/123/reorder'))->assertRedirect($this->siteUrl('/ar/account/login'));
    }

    public function test_a_shared_link_opens_for_a_guest(): void
    {
        $user = User::forceCreate(['name' => 'Owner', 'email' => 'm-'.Str::random(8).'@shop.test', 'password' => 'Secret-pass-123'])->assignRole(UserRole::MERCHANT->value);
        $merchant = Merchant::query()->create([
            'user_id' => $user->id, 'shop_name' => 'محل', 'owner_name' => 'Owner', 'phone' => '0599111222', 'email' => $user->email,
            'commercial_register' => 'CR-1', 'status' => MerchantStatus::APPROVED,
        ]);
        $share = CatalogShare::query()->create(['merchant_id' => $merchant->id, 'token' => Str::random(48), 'locale' => 'en', 'expires_at' => now()->addDays(7)]);

        $response = $this->get($share->url())->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('/en/wholesale/shared-catalog/', $share->url());
    }
}
