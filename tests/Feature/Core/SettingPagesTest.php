<?php

namespace Tests\Feature\Core;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Entities\User;
use Modules\Core\Settings\GeneralSettings;
use Tests\DatabaseTestCase;

class SettingPagesTest extends DatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_the_page_shows_the_settings_and_app_versions(): void
    {
        AppVersion::query()->delete();
        AppVersion::query()->create($this->version('android'));

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin/settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Core/Settings/Index')
                ->where('settings.site_name', app(GeneralSettings::class)->site_name)
                ->where('appVersions.0.platform', 'android')
                ->where('appVersions.0.maintenance_mode', false)
                ->where('logo', []));
    }

    public function test_saving_updates_the_settings(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['site_name' => 'New Name']))
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('New Name', app(GeneralSettings::class)->refresh()->site_name);
    }

    public function test_the_repeater_adds_updates_and_removes_platforms(): void
    {
        AppVersion::query()->delete();
        AppVersion::query()->create($this->version('android'));
        AppVersion::query()->create($this->version('old'));

        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload([
                'app_versions' => [
                    [...$this->version('android'), 'latest_version' => '9.9.9', 'maintenance_mode' => '1'],
                    $this->version('ios'),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['android', 'ios'], AppVersion::query()->pluck('platform')->all());

        $android = AppVersion::query()->where('platform', 'android')->firstOrFail();
        $this->assertSame('9.9.9', $android->latest_version);
        $this->assertTrue($android->maintenance_mode);
    }

    public function test_without_a_repeater_in_the_request_the_platforms_stay(): void
    {
        AppVersion::query()->delete();
        AppVersion::query()->create($this->version('android'));

        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AppVersion::query()->count());
    }

    public function test_validation_errors_are_returned(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload([
                'site_name' => '',
                'email_address_for_contact' => 'nope',
                'app_versions' => [
                    [...$this->version('same'), 'store_url' => 'javascript:alert(1)'],
                    $this->version('same'),
                ],
            ]))
            ->assertSessionHasErrors(['site_name', 'email_address_for_contact', 'app_versions.0.store_url', 'app_versions.1.platform']);
    }

    public function test_the_logo_is_uploaded_shown_and_removed(): void
    {
        Storage::fake('media');

        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['logo' => UploadedFile::fake()->image('logo.png')]))
            ->assertSessionHasNoErrors();

        $this->assertNotEmpty(app(GeneralSettings::class)->refresh()->site_logo_url);

        $this->actingAs($this->admin)
            ->get($this->siteUrl('/ar/admin/settings'))
            ->assertInertia(fn (Assert $page) => $page->has('logo', 1));

        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['remove_logo' => true]))
            ->assertSessionHasNoErrors();

        $this->assertNull(app(GeneralSettings::class)->refresh()->site_logo_url);
    }

    public function test_a_logo_must_be_an_image(): void
    {
        $this->actingAs($this->admin)
            ->put($this->siteUrl('/ar/admin/settings'), $this->payload(['logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')]))
            ->assertSessionHasErrors('logo');
    }

    public function test_viewing_and_saving_need_their_permissions(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v@example.com', 'password' => 'secret-password'])->givePermissionTo('Setting View List');
        $other = User::forceCreate(['name' => 'O', 'email' => 'o@example.com', 'password' => 'secret-password'])->givePermissionTo('Currency View List');

        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/settings'))->assertOk();
        $this->actingAs($viewer)->put($this->siteUrl('/ar/admin/settings'), $this->payload())->assertForbidden();
        $this->actingAs($other)->get($this->siteUrl('/ar/admin/settings'))->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function version(string $platform): array
    {
        return [
            'platform' => $platform,
            'store_url' => 'https://example.com/'.$platform,
            'min_version' => '1.0.0',
            'latest_version' => '1.1.0',
            'maintenance_mode' => false,
            'message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'site_name' => 'Site',
            'email_address_for_contact' => 'contact@example.com',
            'site_description' => 'Description',
            ...$overrides,
        ];
    }
}
