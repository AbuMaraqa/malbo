<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Theme\ThemeService;

class AppearancePageTest extends StorefrontTestCase
{
    private const URL = '/ar/admin/store/appearance';

    /** @var array<int, string> */
    private array $stored = [];

    protected function tearDown(): void
    {
        if ($this->app) {
            Storage::disk('public')->delete($this->stored);
        }

        parent::tearDown();
    }

    public function test_staff_sees_the_current_values(): void
    {
        app(StoreAppearanceService::class)->save(['hero_title' => ['ar' => 'أهلاً', 'en' => 'Welcome'], 'primary_color' => '#0F766E'], ['whatsapp' => '970599000000'], []);

        $this->actingAs($this->owner())->get($this->siteUrl(self::URL))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Appearance/Index')
                ->where('settings.primary_color', '#0F766E')
                ->where('settings.hero_title.ar', 'أهلاً')
                ->where('settings.hero_title.en', 'Welcome')
                ->where('settings.whatsapp', '970599000000')
                ->has('presets', 8)
                ->has('fonts', 10)
                ->where('maxValues', 3));
    }

    public function test_saving_updates_the_settings(): void
    {
        $this->save([
            'primary_color' => '#6B2D5C',
            'font' => 'cairo',
            'hero_title' => ['ar' => 'متجرنا', 'en' => 'Our store'],
            'story_text' => ['ar' => 'قصة', 'en' => ''],
            'values' => [['title' => ['ar' => 'جودة', 'en' => 'Quality'], 'text' => ['ar' => 'نص', 'en' => 'Text']]],
            'whatsapp' => '+970 599 000 000',
            'instagram_url' => 'https://instagram.com/maison',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appearance = app(StoreAppearanceSettings::class);
        $this->assertSame('#6B2D5C', $appearance->primary_color);
        $this->assertSame('cairo', $appearance->font);
        $this->assertSame(['ar' => 'متجرنا', 'en' => 'Our store'], $appearance->hero_title);
        $this->assertSame('قصة', $appearance->story_text['ar']);
        $this->assertSame('Quality', $appearance->values[0]['title']['en']);
        $this->assertSame('970599000000', app(StoreContactSettings::class)->whatsapp);
        $this->assertSame('https://instagram.com/maison', app(StoreContactSettings::class)->instagram_url);
    }

    public function test_saving_clears_the_cached_theme(): void
    {
        $this->assertStringContainsString('--brand:#1F6F50', app(ThemeService::class)->css());

        $this->save(['primary_color' => '#6B2D5C'])->assertSessionHasNoErrors();

        $this->assertStringContainsString('--brand:#6B2D5C', app(ThemeService::class)->css());
    }

    public function test_an_empty_values_list_clears_the_values(): void
    {
        $this->save(['values' => [['title' => ['ar' => 'جودة', 'en' => ''], 'text' => ['ar' => '', 'en' => '']]]]);
        $this->assertCount(1, app(StoreAppearanceSettings::class)->values);

        // A form with files sends no key at all for an empty list.
        $this->save([]);
        $this->assertSame([], app(StoreAppearanceSettings::class)->values);
    }

    public function test_more_values_than_allowed_is_a_form_error(): void
    {
        $value = ['title' => ['ar' => 'ق', 'en' => ''], 'text' => ['ar' => '', 'en' => '']];

        $this->save(['values' => [$value, $value, $value, $value]])->assertSessionHasErrors('values');
    }

    public function test_invalid_color_font_and_links_are_form_errors(): void
    {
        $this->save(['primary_color' => 'red', 'font' => 'comic-sans', 'x_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors(['primary_color', 'font', 'x_url']);

        $this->assertSame('#1F6F50', app(StoreAppearanceSettings::class)->primary_color);
    }

    public function test_staff_without_the_permission_is_refused(): void
    {
        $user = $this->staff(['Product View List']);

        $this->actingAs($user)->get($this->siteUrl(self::URL))->assertForbidden();
        $this->actingAs($user)->put($this->siteUrl(self::URL), ['primary_color' => '#000000', 'font' => 'cairo'])->assertForbidden();
    }

    public function test_a_customer_is_sent_to_the_storefront(): void
    {
        $customer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer-'.Str::random(8).'@store.test', 'password' => 'password', 'email_verified_at' => now()])
            ->assignRole(UserRole::CUSTOMER->value);

        $this->actingAs($customer)->get($this->siteUrl(self::URL))->assertRedirect();
    }

    public function test_logo_upload_keep_replace_and_clear(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors();
        $first = $this->remember(app(StoreAppearanceSettings::class)->logo_path);
        $this->assertNotNull($first);
        $this->assertTrue(Storage::disk('public')->exists($first));

        // Saving other fields keeps the logo.
        $this->save(['hero_title' => ['ar' => 'جديد', 'en' => '']]);
        $this->assertSame($first, app(StoreAppearanceSettings::class)->logo_path);

        // A new file replaces it.
        $this->save(['logo' => UploadedFile::fake()->image('logo2.png')]);
        $second = $this->remember(app(StoreAppearanceSettings::class)->logo_path);
        $this->assertNotSame($first, $second);
        $this->assertFalse(Storage::disk('public')->exists($first));

        // Removing it clears the setting and the file.
        $this->save(['removed_images' => ['logo']]);
        $this->assertNull(app(StoreAppearanceSettings::class)->logo_path);
        $this->assertFalse(Storage::disk('public')->exists($second));
    }

    public function test_svg_logo_is_a_form_error(): void
    {
        $this->save(['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')])
            ->assertSessionHasErrors('logo');

        $this->assertNull(app(StoreAppearanceSettings::class)->logo_path);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function save(array $fields)
    {
        return $this->actingAs($this->owner())->put($this->siteUrl(self::URL), [
            'primary_color' => '#1F6F50',
            'font' => 'tajawal',
            ...$fields,
        ]);
    }

    private function remember(?string $path): ?string
    {
        if ($path) {
            $this->stored[] = $path;
        }

        return $path;
    }

    private function owner(): User
    {
        return $this->staff(['Appearance Update']);
    }
}
