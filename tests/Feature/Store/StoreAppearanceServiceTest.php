<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Tests\TenantTestCase;

class StoreAppearanceServiceTest extends TenantTestCase
{
    /** @var array<int, string> */
    private array $stored = [];

    protected function tearDown(): void
    {
        if ($this->app && tenancy()->initialized) {
            Storage::disk('public')->delete($this->stored);
        }

        parent::tearDown();
    }

    public function test_saves_texts_per_locale_values_and_contact(): void
    {
        app(StoreAppearanceService::class)->save(
            [
                'hero_title' => ['ar' => 'أهلاً', 'en' => 'Welcome'],
                'values' => [
                    ['title' => ['ar' => 'جودة', 'en' => 'Quality'], 'text' => ['ar' => 'نص', 'en' => 'Text']],
                    ['title' => ['ar' => '', 'en' => ''], 'text' => ['ar' => '', 'en' => '']],
                    ['title' => ['ar' => 'ثانية', 'en' => ''], 'text' => ['ar' => '', 'en' => '']],
                    ['title' => ['ar' => 'ثالثة', 'en' => ''], 'text' => ['ar' => '', 'en' => '']],
                    ['title' => ['ar' => 'رابعة', 'en' => ''], 'text' => ['ar' => '', 'en' => '']],
                ],
            ],
            ['whatsapp' => '+970 59-900-0000', 'phone' => '022222222', 'address' => ['ar' => 'رام الله', 'en' => '']],
            [],
        );

        $appearance = app(StoreAppearanceSettings::class);
        $this->assertSame(['ar' => 'أهلاً', 'en' => 'Welcome'], $appearance->hero_title);
        $this->assertCount(3, $appearance->values); // empty items dropped, at most 3 kept
        $this->assertSame('جودة', $appearance->values[0]['title']['ar']);

        $contact = app(StoreContactSettings::class);
        $this->assertSame('970599000000', $contact->whatsapp);
        $this->assertSame(['ar' => 'رام الله', 'en' => ''], $contact->address);
    }

    public function test_stores_images_and_replaces_the_old_file(): void
    {
        $service = app(StoreAppearanceService::class);

        $service->save([], [], ['logo' => UploadedFile::fake()->image('logo.png')]);
        $first = app(StoreAppearanceSettings::class)->logo_path;
        $this->stored[] = $first;
        $this->assertNotNull($first);
        $this->assertTrue(Storage::disk('public')->exists($first));

        $service->save([], [], ['logo' => UploadedFile::fake()->image('logo2.png')]);
        $second = app(StoreAppearanceSettings::class)->logo_path;
        $this->stored[] = $second;

        $this->assertNotSame($first, $second);
        $this->assertFalse(Storage::disk('public')->exists($first));
        $this->assertTrue(Storage::disk('public')->exists($second));
    }

    public function test_svg_and_other_non_raster_files_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        try {
            app(StoreAppearanceService::class)->save([], [], ['logo' => $svg]);
            $this->fail('An SVG upload must be rejected.');
        } catch (ValidationException) {
            $this->assertNull(app(StoreAppearanceSettings::class)->logo_path);
        }
    }

    public function test_social_links_must_be_web_urls(): void
    {
        $this->expectException(ValidationException::class);

        app(StoreAppearanceService::class)->save([], ['instagram_url' => 'data://text/html,hi'], []);
    }

    public function test_an_image_can_be_removed(): void
    {
        $service = app(StoreAppearanceService::class);
        $service->save([], [], ['hero_image' => UploadedFile::fake()->image('hero.jpg')]);
        $path = app(StoreAppearanceSettings::class)->hero_image_path;
        $this->stored[] = $path;

        $service->save([], [], ['hero_image' => false]);

        $this->assertNull(app(StoreAppearanceSettings::class)->hero_image_path);
        $this->assertFalse(Storage::disk('public')->exists($path));
    }
}
