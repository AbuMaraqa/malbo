<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Store\Entities\HeroSlide;
use Modules\Store\Services\HeroSlideService;

/**
 * The home page slider: slides staff set up (a picture, another for phones if they want, texts
 * per language, a link, an order, a switch and dates). The home shows the live ones in their order;
 * without any, it keeps building its own slides.
 */
class HeroSlidesTest extends StorefrontTestCase
{
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->staff = $this->staff(['Hero Slide View List', 'Hero Slide Create', 'Hero Slide Update', 'Hero Slide Delete']);
    }

    public function test_crud_filters_and_permissions(): void
    {
        $this->actingAs($this->staff)->post($this->admin('/slider'), [
            'eyebrow' => 'تشكيلة الشتاء',
            'title' => 'دفء بأناقة',
            'subtitle' => 'قطع جديدة كل أسبوع',
            'button_label' => 'تسوّقي الآن',
            'link' => '/ar/shop',
            'sort_order' => 2,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('wide.jpg', 1920, 900),
            'mobile_image' => UploadedFile::fake()->image('tall.jpg', 1080, 1350),
        ])->assertSessionHasNoErrors()->assertRedirect($this->admin('/slider'));

        $slide = HeroSlide::query()->with('translations')->sole();
        $this->assertSame(['دفء بأناقة', '/ar/shop', 2], [$slide->translate('ar')->title, $slide->link, $slide->sort_order]);
        $this->assertNotNull($slide->getFirstMedia('image'));
        $this->assertNotNull($slide->getFirstMedia('mobile_image'));

        $hidden = $this->slide(['is_active' => false, 'sort_order' => 5]);

        $this->actingAs($this->staff)->get($this->admin('/slider'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/HeroSlide/Index')
            ->where('table.meta.total', 2)
            ->where('table.data.0.title', 'دفء بأناقة'));
        $this->actingAs($this->staff)->get($this->admin('/slider?filter[live]=0'))->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $hidden->id));

        $this->actingAs($this->staff)->get($this->admin('/slider/'.$slide->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/HeroSlide/Edit')->where('slide.title', 'دفء بأناقة')->has('slide.mobile_images', 1));

        // English texts are another translation; the Arabic stay, and so does the wide picture.
        $this->actingAs($this->staff)->post($this->admin('/slider/'.$slide->id, 'en'), [
            '_method' => 'put', 'title' => 'Warm and elegant', 'link' => 'https://example.com/winter', 'sort_order' => 1, 'is_active' => true, 'remove_mobile_image' => true,
        ])->assertSessionHasNoErrors();
        $slide->refresh()->load('translations');
        $this->assertSame(['دفء بأناقة', 'Warm and elegant'], [$slide->translate('ar')->title, $slide->translate('en')->title]);
        $this->assertNotNull($slide->getFirstMedia('image'), 'the picture stays when none is sent');
        $this->assertNull($slide->getFirstMedia('mobile_image'), 'the phones picture can be removed');

        foreach (['javascript:alert(1)', '//evil.example', 'http://example.com', 'shop'] as $link) {
            $this->actingAs($this->staff)->post($this->admin('/slider'), ['link' => $link, 'image' => UploadedFile::fake()->image('x.jpg')])->assertSessionHasErrors('link');
        }
        $this->actingAs($this->staff)->post($this->admin('/slider'), ['title' => 'no picture'])->assertSessionHasErrors('image');
        $this->actingAs($this->staff)->post($this->admin('/slider'), [
            'starts_at' => '2026-12-10 10:00', 'ends_at' => '2026-12-01 10:00', 'image' => UploadedFile::fake()->image('x.jpg'),
        ])->assertSessionHasErrors('ends_at');

        $this->actingAs($this->staff)->put($this->admin('/slider/'.$hidden->id.'/status'), ['active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($hidden->fresh()->is_active);

        $this->actingAs($this->staff)->delete($this->admin('/slider/'.$slide->id))->assertSessionHasNoErrors();
        $this->assertNull($slide->fresh());

        $reader = $this->staff(['Hero Slide View List']);
        $this->actingAs($reader)->delete($this->admin('/slider/'.$hidden->id))->assertForbidden();
        $this->actingAs($reader)->get($this->admin('/slider/add'))->assertForbidden();
    }

    public function test_the_home_shows_live_slides_in_their_order(): void
    {
        $this->assertSame([], $this->props('/ar', 'Store/Storefront/Home')['slides'], 'without slides the hero builds its own');

        $second = $this->slide(['sort_order' => 2], ['ar' => ['title' => 'الثانية']]);
        $first = $this->slide(['sort_order' => 1, 'link' => '/ar/shop'], ['ar' => ['title' => 'الأولى', 'button_label' => 'تسوّق']]);
        $this->slide(['is_active' => false], ['ar' => ['title' => 'مخفية']]);
        $this->slide(['starts_at' => now()->addDay()], ['ar' => ['title' => 'لاحقًا']]);
        $this->slide(['ends_at' => now()->subDay()], ['ar' => ['title' => 'انتهت']]);

        $slides = $this->props('/ar', 'Store/Storefront/Home')['slides'];

        $this->assertSame([$first->id, $second->id], array_column($slides, 'id'));
        $this->assertSame(['الأولى', 'تسوّق', '/ar/shop'], [$slides[0]['title'], $slides[0]['button'], $slides[0]['url']]);
        $this->assertNotEmpty($slides[0]['image']);
        $this->assertNull($slides[0]['mobile_image']);
    }

    public function test_texts_come_from_one_language(): void
    {
        $this->slide([], ['ar' => ['eyebrow' => 'جديد', 'title' => 'عنوان']]);

        // English has no texts of its own: the Arabic ones together, never mixed.
        $this->assertSame(['جديد', 'عنوان'], [
            $this->props('/en', 'Store/Storefront/Home')['slides'][0]['eyebrow'],
            $this->props('/en', 'Store/Storefront/Home')['slides'][0]['title'],
        ]);

        $picture = $this->slide();
        $alone = collect($this->props('/ar', 'Store/Storefront/Home')['slides'])->firstWhere('id', $picture->id);
        $this->assertSame([null, null, null, null], [$alone['eyebrow'], $alone['title'], $alone['subtitle'], $alone['button']], 'a picture alone');
    }

    /**
     * @param  array<string, mixed>  $columns
     * @param  array<string, array<string, string>>  $texts  locale => [field => text]
     */
    private function slide(array $columns = [], array $texts = []): HeroSlide
    {
        $service = app(HeroSlideService::class);
        $slide = $service->create([...$columns, 'is_active' => $columns['is_active'] ?? true, 'image' => UploadedFile::fake()->image('s.jpg', 600, 300)], 'ar', $this->staff->id);

        foreach ($texts as $locale => $fields) {
            $service->update($slide, [...$columns, ...$fields, 'is_active' => $columns['is_active'] ?? true], $locale);
        }

        return $slide->fresh();
    }

    private function admin(string $path, string $locale = 'ar'): string
    {
        return $this->siteUrl('/'.$locale.'/admin/store'.$path);
    }
}
