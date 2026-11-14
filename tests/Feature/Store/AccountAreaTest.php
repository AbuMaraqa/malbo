<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\Entities\City;
use Modules\Store\Entities\Address;
use Modules\Store\Services\AddressService;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * The account's profile, password and saved addresses.
 */
class AccountAreaTest extends StorefrontTestCase
{
    use MakesShippingZones;

    private User $buyer;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0599000111', 'password' => 'Secret-pass-123'])
            ->assignRole(UserRole::CUSTOMER->value);
        $this->city = $this->city('رام الله', ['المصيون']);
        $this->zone([$this->city]);
        $this->actingAs($this->buyer);
    }

    public function test_profile_shows_and_saves(): void
    {
        $props = $this->props('/ar/account', 'Store/Storefront/Account/Profile');
        $this->assertSame(['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0599000111'], $props['profile']);

        $this->put($this->siteUrl('/ar/account/profile'), ['name' => 'New Name', 'email' => 'New@Example.com', 'phone' => '+970 599 222 333', 'current_password' => 'Secret-pass-123'])->assertSessionHasNoErrors();

        $this->assertSame(['New Name', 'new@example.com', '0599222333'], [$this->buyer->fresh()->name, $this->buyer->fresh()->email, $this->buyer->fresh()->phone]);
    }

    public function test_email_must_stay_unique(): void
    {
        User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x-secret-123']);

        $this->from($this->siteUrl('/ar/account'))->put($this->siteUrl('/ar/account/profile'), ['name' => 'B', 'email' => 'OTHER@example.com', 'phone' => '0599000111'])
            ->assertSessionHasErrors('email');
        // Its own email is fine.
        $this->put($this->siteUrl('/ar/account/profile'), ['name' => 'B', 'email' => 'buyer@example.com', 'phone' => '0599000111'])->assertSessionHasNoErrors();
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $profile = fn (array $extra) => $this->from($this->siteUrl('/ar/account'))
            ->put($this->siteUrl('/ar/account/profile'), ['name' => 'Buyer', 'email' => 'moved@example.com', 'phone' => '0599000111', ...$extra]);

        $profile([])->assertSessionHasErrors('current_password');
        $profile(['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame('buyer@example.com', $this->buyer->fresh()->email);

        $profile(['current_password' => 'Secret-pass-123'])->assertSessionHasNoErrors();
        $this->assertSame('moved@example.com', $this->buyer->fresh()->email);

        // The name and phone alone need no password.
        $this->put($this->siteUrl('/ar/account/profile'), ['name' => 'Renamed', 'email' => 'MOVED@example.com', 'phone' => '0599000111'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $this->buyer->fresh()->name);
    }

    public function test_a_password_change_signs_out_the_other_devices(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Illuminate\Auth\Events\OtherDeviceLogout::class]);
        $sessionBefore = session()->getId();

        $this->put($this->siteUrl('/ar/account/password'), [
            'current_password' => 'Secret-pass-123',
            'password' => 'New-secret-456',
            'password_confirmation' => 'New-secret-456',
        ])->assertSessionHasNoErrors();

        \Illuminate\Support\Facades\Event::assertDispatched(\Illuminate\Auth\Events\OtherDeviceLogout::class);
        $this->assertNotSame($sessionBefore, session()->getId(), 'the session was not regenerated');
        $this->assertAuthenticatedAs($this->buyer);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $this->from($this->siteUrl('/ar/account'))->put($this->siteUrl('/ar/account/password'), [
            'current_password' => 'wrong',
            'password' => 'New-secret-456',
            'password_confirmation' => 'New-secret-456',
        ])->assertSessionHasErrors('current_password');

        $this->put($this->siteUrl('/ar/account/password'), [
            'current_password' => 'Secret-pass-123',
            'password' => 'New-secret-456',
            'password_confirmation' => 'New-secret-456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New-secret-456', $this->buyer->fresh()->password));
    }

    public function test_add_list_edit_delete(): void
    {
        $this->saveAddress()->assertSessionHasNoErrors();

        $props = $this->props('/ar/account/addresses', 'Store/Storefront/Account/Addresses');
        $this->assertSame([$this->city->id], array_column($props['cities'], 'value'));
        $this->assertCount(1, $props['addresses']);
        $row = $props['addresses'][0];
        $this->assertSame(['البيت', 'Buyer', '0599000111', 'رام الله', 'المصيون', true], [$row['label'], $row['recipient_name'], $row['phone'], $row['city'], $row['district'], $row['is_default']]);

        $this->put($this->siteUrl('/ar/account/addresses/'.$row['id']), $this->fields(['address_line' => 'شارع جديد']))->assertSessionHasNoErrors();
        $this->assertSame('شارع جديد', Address::query()->sole()->address_line);

        $this->delete($this->siteUrl('/ar/account/addresses/'.$row['id']))->assertSessionHasNoErrors();
        $this->assertSame(0, Address::query()->count());
    }

    public function test_only_one_default(): void
    {
        $this->saveAddress(['label' => 'أ']);
        $this->saveAddress(['label' => 'ب']);
        $this->saveAddress(['label' => 'ج', 'is_default' => true]);

        $this->assertSame(['ج'], Address::query()->where('is_default', true)->pluck('label')->all());

        $first = Address::query()->where('label', 'أ')->sole();
        $this->put($this->siteUrl('/ar/account/addresses/'.$first->id.'/default'))->assertSessionHasNoErrors();
        $this->assertSame(['أ'], Address::query()->where('is_default', true)->pluck('label')->all());
    }

    public function test_deleting_the_default_moves_it(): void
    {
        $this->saveAddress(['label' => 'أ']);
        $this->saveAddress(['label' => 'ب']);
        $default = Address::query()->where('is_default', true)->sole();

        $this->delete($this->siteUrl('/ar/account/addresses/'.$default->id));

        $this->assertSame(['ب'], Address::query()->where('is_default', true)->pluck('label')->all());
    }

    public function test_city_must_be_deliverable(): void
    {
        $far = $this->city('بعيدة');

        $this->saveAddress(['city_id' => $far->id, 'district_id' => null])->assertSessionHasErrors('city_id');
        $this->saveAddress(['district_id' => null])->assertSessionHasErrors('district_id');
        $this->saveAddress(['phone' => '12'])->assertSessionHasErrors('phone');
        $this->assertSame(0, Address::query()->count());
    }

    public function test_another_users_address_is_404(): void
    {
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x-secret-123']);
        $theirs = app(AddressService::class)->create($other, $this->fields());

        $this->put($this->siteUrl('/ar/account/addresses/'.$theirs->id), $this->fields())->assertNotFound();
        $this->delete($this->siteUrl('/ar/account/addresses/'.$theirs->id))->assertNotFound();
        $this->assertSame(1, Address::query()->count());
    }

    public function test_address_limit(): void
    {
        for ($i = 0; $i < AddressService::LIMIT; $i++) {
            app(AddressService::class)->create($this->buyer, $this->fields());
        }

        $this->saveAddress()->assertSessionHasErrors('address');
    }

    public function test_guests_cannot_reach_the_account(): void
    {
        auth()->logout();

        foreach (['/ar/account', '/ar/account/addresses', '/ar/account/orders', '/ar/account/wishlist'] as $path) {
            $this->get($this->siteUrl($path))->assertRedirect(route('store.account.login'));
        }
    }

    public function test_a_guest_is_sent_to_sign_in_before_the_locale_middleware_ran(): void
    {
        auth()->logout();
        // As in a real request: `auth` runs before SetRouteLocale sets the URL defaults.
        URL::defaults(['locale' => null]);

        $this->get($this->siteUrl('/en/account'))->assertRedirect($this->siteUrl('/en/account/login'));
    }

    private function saveAddress(array $overrides = []): TestResponse
    {
        return $this->from($this->siteUrl('/ar/account/addresses'))->post($this->siteUrl('/ar/account/addresses'), $this->fields($overrides));
    }

    private function fields(array $overrides = []): array
    {
        return [
            'label' => 'البيت',
            'recipient_name' => 'Buyer',
            'phone' => '0599000111',
            'city_id' => $this->city->id,
            'district_id' => $this->city->districts()->first()->id,
            'address_line' => 'شارع الإرسال 5',
            'is_default' => false,
            ...$overrides,
        ];
    }
}
