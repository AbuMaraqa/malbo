<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * A merchant signing in on the storefront's login lands in the portal once approved, on the status
 * page before that. A page they asked for first still wins, and buyers keep landing on their account.
 */
class MerchantSignInTest extends WholesaleTestCase
{
    public function test_an_approved_merchant_lands_on_the_catalog(): void
    {
        $this->login($this->merchant(MerchantStatus::APPROVED))->assertRedirect(route('store.wholesale.catalog'));
    }

    public function test_a_pending_or_suspended_merchant_lands_on_the_status_page(): void
    {
        $this->login($this->merchant(MerchantStatus::PENDING))->assertRedirect(route('store.wholesale.status'));

        auth()->logout();
        $this->flushSession();
        $this->login($this->merchant(MerchantStatus::SUSPENDED))->assertRedirect(route('store.wholesale.status'));
    }

    public function test_the_page_asked_for_first_still_wins(): void
    {
        $merchant = $this->merchant(MerchantStatus::APPROVED);

        $this->get($this->siteUrl('/ar/wholesale/cart'))->assertRedirect(route('store.account.login'));
        $this->login($merchant)->assertRedirect($this->siteUrl('/ar/wholesale/cart'));
    }

    public function test_a_buyer_still_lands_on_the_account(): void
    {
        User::forceCreate(['name' => 'B', 'email' => 'buyer@example.com', 'password' => 'Secret-pass-123'])->assignRole(UserRole::CUSTOMER->value);

        $this->post($this->siteUrl('/ar/account/login'), ['email' => 'buyer@example.com', 'password' => 'Secret-pass-123'])
            ->assertRedirect(route('store.account'));
    }

    private function login(Merchant $merchant)
    {
        RateLimiter::clear('store-login:'.$merchant->user->email.'|127.0.0.1');

        return $this->post($this->siteUrl('/ar/account/login'), ['email' => $merchant->user->email, 'password' => 'Secret-pass-123']);
    }
}
