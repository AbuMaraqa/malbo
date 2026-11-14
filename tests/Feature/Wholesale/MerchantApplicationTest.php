<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\Entities\City;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * The public join form: a Merchant account and a pending profile, signed in, with no prices.
 */
class MerchantApplicationTest extends WholesaleTestCase
{
    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        foreach (['wholesale-apply|127.0.0.1', 'wholesale-apply-minute|127.0.0.1', 'wholesale-apply-attempts|127.0.0.1', 'wholesale-apply-success:127.0.0.1'] as $key) {
            RateLimiter::clear($key);
        }
        $this->city = $this->city('رام الله');
    }

    public function test_the_form_lists_cities(): void
    {
        $props = $this->props('/ar/wholesale/apply', 'Wholesale/Portal/Apply');

        $this->assertContains(['value' => $this->city->id, 'label' => 'رام الله'], $props['cities']);
    }

    public function test_applying_creates_a_pending_merchant_and_signs_in(): void
    {
        $this->apply()->assertRedirect(route('store.wholesale.status'))->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'shop@example.com')->sole();
        $this->assertSame([UserRole::MERCHANT->value], $user->getRoleNames()->all());
        $this->assertAuthenticatedAs($user);

        $merchant = Merchant::query()->where('user_id', $user->id)->sole();
        $this->assertSame(MerchantStatus::PENDING, $merchant->status);
        $this->assertSame('محل النجمة', $merchant->shop_name);
        $this->assertSame('أحمد التاجر', $merchant->owner_name);
        $this->assertSame($this->city->id, $merchant->city_id);
        $this->assertSame('CR-77', $merchant->commercial_register);
        $this->assertSame('شارع القدس', $merchant->shop_address);
        $this->assertNull($merchant->tier_id);
        $this->assertSame([[null, MerchantStatus::PENDING, $user->id]], $merchant->statusChanges->map(fn ($change) => [$change->from_status, $change->to_status, $change->created_by])->all());
    }

    public function test_the_register_document_is_stored_privately(): void
    {
        $this->apply(['register_document' => UploadedFile::fake()->create('register.pdf', 300, 'application/pdf')])->assertSessionHasNoErrors();

        $merchant = Merchant::query()->sole();
        $this->assertSame('register.pdf', $merchant->register_document_name);
        $this->assertStringStartsWith('wholesale/registers/', $merchant->register_document_path);
        Storage::disk('local')->assertExists($merchant->register_document_path);
        Storage::disk('public')->assertMissing($merchant->register_document_path);
    }

    public function test_the_document_must_be_a_pdf_or_image_under_5mb(): void
    {
        $this->apply(['register_document' => UploadedFile::fake()->create('tool.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('register_document');
        $this->apply(['register_document' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('register_document');
        $this->apply(['register_document' => UploadedFile::fake()->image('register.jpg')])->assertSessionHasNoErrors();
    }

    public function test_a_file_named_pdf_that_is_not_a_pdf_is_refused(): void
    {
        // Real bytes of a Windows program (an "MZ" header), named and labelled as a PDF.
        $path = tempnam(sys_get_temp_dir(), 'cr');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00".str_repeat("\x00", 48)."This program cannot be run in DOS mode.\r\n".random_bytes(512));
        $fake = new UploadedFile($path, 'register.pdf', 'application/pdf', null, true);

        $this->apply(['register_document' => $fake])->assertSessionHasErrors('register_document');
        $this->assertSame(0, Merchant::query()->count());

        // A real PDF's bytes go through.
        $pdf = tempnam(sys_get_temp_dir(), 'cr');
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->apply(['register_document' => new UploadedFile($pdf, 'register.pdf', 'application/pdf', null, true)])->assertSessionHasNoErrors();
    }

    public function test_the_email_must_be_free(): void
    {
        User::forceCreate(['name' => 'X', 'email' => 'shop@example.com', 'password' => 'Secret-pass-123']);

        $this->apply(['email' => 'SHOP@example.com'])->assertSessionHasErrors('email');
        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_required_fields(): void
    {
        $this->post($this->siteUrl('/ar/wholesale/apply'), [])
            ->assertSessionHasErrors(['shop_name', 'owner_name', 'city_id', 'phone', 'email', 'commercial_register', 'password']);
    }

    public function test_successful_applications_are_limited_per_ip(): void
    {
        foreach (range(1, 3) as $index) {
            $this->apply(['email' => "shop{$index}@example.com"])->assertSessionHasNoErrors();
            auth()->logout();
        }

        $this->apply(['email' => 'shop9@example.com'])->assertSessionHasErrors(['email' => __('Too many attempts. Try again later.')]);
        $this->assertSame(3, Merchant::query()->count());
    }

    public function test_a_few_mistakes_do_not_lock_the_shop_out(): void
    {
        foreach (range(1, 6) as $ignored) {
            $this->apply(['commercial_register' => ''])->assertSessionHasErrors('commercial_register');
        }

        $this->apply()->assertSessionHasNoErrors();
        $this->assertSame(1, Merchant::query()->count());
    }

    public function test_every_attempt_counts_toward_a_higher_limit(): void
    {
        foreach (range(1, 20) as $ignored) {
            $this->apply(['commercial_register' => ''])->assertSessionHasErrors('commercial_register');
        }

        $this->apply()->assertSessionHasErrors(['email' => __('Too many attempts. Try again later.')]);
        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_the_status_page_shows_pending_review_without_prices(): void
    {
        [$shirt] = $this->shirt();
        $this->apply();

        $props = $this->props('/ar/wholesale/account', 'Wholesale/Portal/Status');

        $this->assertSame('pending', $props['merchant']['status']);
        $this->assertNull($props['nav']);
        $this->assertStringNotContainsString('50.00', json_encode($props));

        $this->get($this->siteUrl('/ar/wholesale'))->assertRedirect(route('store.wholesale.status'));
        $this->get($this->siteUrl('/ar/wholesale/product/'.$shirt->slug))->assertRedirect(route('store.wholesale.status'));
    }

    public function test_a_merchant_can_sign_in_on_the_storefront(): void
    {
        $this->apply();
        auth()->logout();

        $this->post($this->siteUrl('/ar/account/login'), ['email' => 'shop@example.com', 'password' => 'Secret-pass-123'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_a_signed_in_visitor_is_sent_to_the_status_page(): void
    {
        $this->actingAs($this->merchant()->user)->get($this->siteUrl('/ar/wholesale/apply'))->assertRedirect(route('store.wholesale.status'));
    }

    private function apply(array $overrides = []): TestResponse
    {
        return $this->post($this->siteUrl('/ar/wholesale/apply'), [
            'shop_name' => 'محل النجمة',
            'owner_name' => 'أحمد التاجر',
            'city_id' => $this->city->id,
            'phone' => '0599123456',
            'email' => 'shop@example.com',
            'commercial_register' => 'CR-77',
            'shop_address' => 'شارع القدس',
            'password' => 'Secret-pass-123',
            'password_confirmation' => 'Secret-pass-123',
            ...$overrides,
        ]);
    }
}
