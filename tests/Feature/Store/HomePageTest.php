<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Store\Services\StoreAppearanceService;

class HomePageTest extends StorefrontTestCase
{
    private const HOME = 'Store/Storefront/Home';

    public function test_home_shows_the_store_content(): void
    {
        $this->siteName('Maison Test');
        app(StoreAppearanceService::class)->save([
            'primary_color' => '#6B2D5C',
            'announcement' => ['ar' => 'توصيل مجاني', 'en' => 'Free delivery'],
            'hero_eyebrow' => ['ar' => 'منذ ١٩٩٨', 'en' => 'Since 1998'],
            'hero_title' => ['ar' => 'أناقة كل يوم', 'en' => 'Everyday elegance'],
            'hero_subtitle' => ['ar' => 'قطع مختارة بعناية', 'en' => 'Carefully chosen pieces'],
            'story_title' => ['ar' => 'حكايتنا', 'en' => 'Our story'],
            'story_text' => ['ar' => 'بدأنا من محل صغير', 'en' => 'We started small'],
            'values' => [['title' => ['ar' => 'جودة', 'en' => 'Quality'], 'text' => ['ar' => 'خامات أصلية', 'en' => 'Real materials']]],
        ], ['whatsapp' => '970599000000', 'phone' => '022950000', 'instagram_url' => 'https://instagram.com/maison'], []);
        Category::factory()->create(['name' => 'فساتين']);
        Product::factory()->create(['name' => 'فستان سهرة', 'base_price' => 250, 'sale_price' => 199, 'is_featured' => 1]);
        Branch::factory()->create();

        $props = $this->props('/ar', self::HOME);

        $this->assertSame('Maison Test', $props['store']['name']);
        $this->assertSame('توصيل مجاني', $props['store']['announcement']);
        $this->assertSame('منذ ١٩٩٨', $props['hero']['eyebrow']);
        $this->assertSame('أناقة كل يوم', $props['hero']['title']);
        $this->assertSame('قطع مختارة بعناية', $props['hero']['subtitle']);
        $this->assertSame('فساتين', $props['categories'][0]['name']);
        $this->assertSame('فستان سهرة', $props['signature']['name']);
        $this->assertSame('199.00 ₪', $props['signature']['price']['label']);
        $this->assertSame('250.00 ₪', $props['signature']['price']['was']);
        $this->assertSame('بدأنا من محل صغير', $props['story']['text']);
        $this->assertSame('خامات أصلية', $props['values'][0]['text']);
        $this->assertSame('022950000', $props['store']['contact']['phone']);
        $this->assertSame([['label' => 'Instagram', 'url' => 'https://instagram.com/maison']], $props['store']['socials']);
        $this->assertNotSame([], $props['store']['branches']);

        // The store's color reaches the first paint, in the root view.
        $this->get($this->siteUrl('/ar'))->assertSee('--brand:#6B2D5C', false);
    }

    public function test_english_home_uses_english_texts_with_arabic_fallback(): void
    {
        app(StoreAppearanceService::class)->save([
            'hero_title' => ['ar' => 'أناقة كل يوم', 'en' => 'Everyday elegance'],
            'hero_subtitle' => ['ar' => 'نص عربي فقط', 'en' => ''],
        ], [], []);

        $this->get($this->siteUrl('/en'))->assertOk()->assertSee('<html lang="en" dir="ltr"', false);

        $props = $this->props('/en', self::HOME);
        $this->assertSame('Everyday elegance', $props['hero']['title']);
        $this->assertSame('نص عربي فقط', $props['hero']['subtitle']);
    }

    public function test_language_switch_stays_on_the_store_host(): void
    {
        $props = $this->props('/ar/shop?q=x', 'Store/Storefront/Shop');

        $languages = collect($props['store']['languages'])->keyBy('code');

        $this->assertSame(array_keys(config('app.locales')), $languages->keys()->all());
        $this->assertSame($this->siteUrl('/en/shop?q=x'), $languages['en']['url']);
        $this->assertTrue($languages['ar']['current']);
        $this->assertFalse($languages['en']['current']);
        $this->assertStringEndsWith('/images/flags/SA.svg', $languages['ar']['flag']);
    }

    public function test_arabic_home_is_rtl(): void
    {
        $this->get($this->siteUrl('/ar'))->assertOk()->assertSee('<html lang="ar" dir="rtl"', false);
    }

    public function test_empty_store_home_renders_without_empty_sections(): void
    {
        $this->siteName('Empty Store');

        $props = $this->props('/ar', self::HOME);

        $this->assertSame('Empty Store', $props['hero']['title']); // the store name stands in for the hero title
        $this->assertNull($props['store']['announcement']);
        $this->assertSame([], $props['categories']);
        $this->assertNull($props['signature']);
        $this->assertSame([], $props['featured']);
        $this->assertArrayNotHasKey('heroPiece', $props);
        $this->assertNull($props['story']);
        $this->assertSame([], $props['values']);
        $this->assertNull($props['store']['contact']);
        $this->assertSame([], $props['store']['branches']);
        $this->assertSame([], $props['store']['socials']);
    }

    public function test_new_arrivals_list_the_newest_products_first(): void
    {
        $older = Product::factory()->create(['name' => 'قميص قديم']);
        $newer = Product::factory()->create(['name' => 'فستان جديد']);
        $older->forceFill(['created_at' => now()->subWeek()])->save();

        $arrivals = $this->props('/ar', self::HOME)['arrivals'];

        $this->assertSame([$newer->id, $older->id], array_column($arrivals, 'id'));
    }

    public function test_best_sellers_list_what_sold_most_first(): void
    {
        $sold = Product::factory()->create(['name' => 'حذاء رياضي']);
        $newer = Product::factory()->create(['name' => 'جاكيت']);
        // SalesCounter writes the column in the app; a test sets it directly.
        $sold->forceFill(['sales_count' => 12, 'created_at' => now()->subWeek()])->save();

        $best = $this->props('/ar', self::HOME)['bestSellers'];

        $this->assertSame([$sold->id, $newer->id], array_column($best, 'id'));
    }

    public function test_every_page_gets_the_header_categories_and_the_members_link(): void
    {
        $category = Category::factory()->create(['name' => 'فساتين']);
        app(StoreAppearanceService::class)->save(['membership_enabled' => true], [], []);

        $store = $this->props('/ar', self::HOME)['store'];

        $this->assertSame($category->id, $store['categories'][0]['id']);
        $this->assertSame('فساتين', $store['categories'][0]['name']);
        $this->assertSame(route('store.category', ['slug' => $category->slug]), $store['categories'][0]['url']);
        $this->assertSame(route('store.members'), $store['members_url']);

        app(StoreAppearanceService::class)->save(['membership_enabled' => false], [], []);
        $this->assertNull($this->props('/ar', self::HOME)['store']['members_url']);
    }

    public function test_the_layout_invites_buyers_to_wholesale_and_sends_merchants_to_the_portal(): void
    {
        $guest = $this->props('/ar', self::HOME)['store']['wholesale'];

        $this->assertFalse($guest['merchant']);
        $this->assertSame(route('store.wholesale.apply'), $guest['apply_url']);
        $this->assertSame(route('store.wholesale.catalog'), $guest['portal_url']);
        $this->assertSame(['merchant', 'portal_url', 'apply_url'], array_keys($guest), 'links only, never a price');

        $merchant = User::forceCreate(['name' => 'Shop', 'email' => 'shop-'.Str::random(8).'@store.test', 'password' => 'secret-password'])
            ->assignRole(UserRole::MERCHANT->value);
        $this->actingAs($merchant);

        $this->assertTrue($this->props('/ar', self::HOME)['store']['wholesale']['merchant']);
    }

    public function test_each_section_is_only_rendered_with_content(): void
    {
        $page = file_get_contents(module_path('Store', 'resources/js/Pages/Storefront/Home.vue'));

        foreach (['promises.length', 'categories.length', '"signature"', 'arrivals.length', '"story"', 'values.length', '"membership"'] as $condition) {
            $this->assertStringContainsString('v-if='.(str_starts_with($condition, '"') ? $condition : '"'.$condition.'"'), $page);
        }
    }

    public function test_the_home_lists_are_cached_and_a_write_refreshes_them(): void
    {
        Category::factory()->create(['name' => 'فساتين']);
        Product::factory()->create(['name' => 'فستان سهرة']);
        $this->props('/ar', self::HOME);

        // A row changed behind the models' back stays cached: this page view did not read it.
        Product::query()->toBase()->update(['base_price' => 777]);
        $cached = $this->props('/ar', self::HOME);
        $this->assertNotSame('777.00 ₪', $cached['signature']['price']['label']);

        // A write through the model refreshes the list.
        Product::factory()->create(['name' => 'حقيبة جلد']);
        $this->assertSame('777.00 ₪', $this->props('/ar', self::HOME)['featured'][0]['price']['label']);

        Category::factory()->create(['name' => 'حقائب']);
        $this->assertCount(2, $this->props('/ar', self::HOME)['categories']);
    }

    public function test_a_cached_list_keeps_prices_current_when_a_discount_window_closes(): void
    {
        Product::factory()->create(['base_price' => 100, 'discount_type' => 'percentage', 'discount_value' => 50, 'discount_end_date' => now()->addDay()->toDateTimeString()]);

        $this->assertSame('50.00 ₪', $this->props('/ar', self::HOME)['signature']['price']['label']);

        $this->travel(3)->days();

        $this->assertSame('100.00 ₪', $this->props('/ar', self::HOME)['signature']['price']['label']);
    }

    public function test_a_full_home_stays_within_the_query_budget(): void
    {
        Category::factory()->count(4)->create();
        Product::factory()->count(9)->create();
        Branch::factory()->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get($this->siteUrl('/ar'))->assertOk();
        $cold = $queries;

        $queries = 0;
        $this->get($this->siteUrl('/ar'))->assertOk();
        $cached = $queries;

        $this->assertLessThan(25, $cached, "A cached home ran {$cached} queries");
        $this->assertLessThan($cold, $cached, "The cache saved nothing: {$cold} cold, {$cached} cached");
    }

    public function test_no_dark_sections(): void
    {
        Product::factory()->create();

        $html = $this->get($this->siteUrl('/ar'))->assertOk()->getContent();

        foreach (['#151716', '#111312', 'bg-black', 'bg-neutral-900'] as $dark) {
            $this->assertStringNotContainsString($dark, $html);
        }
    }

    public function test_without_its_own_logo_the_store_shows_the_malbo_identity(): void
    {
        $props = $this->props('/ar', self::HOME);

        $this->assertStringEndsWith('/brand/malbo-logo.svg', $props['store']['logo']);
        $this->assertStringEndsWith('/brand/malbo-logo-light.svg', $props['store']['logo_light']);
        // A share card wants a photo, not the brand's logo.
        $this->assertNull($props['store']['share_image']);
        $this->get($this->siteUrl('/ar'))->assertSee('/brand/favicon.svg', false)->assertSee('--brand:#6D1C2F', false);
    }

    public function test_an_uploaded_logo_replaces_the_malbo_logo(): void
    {
        Storage::fake('public');
        app(StoreAppearanceService::class)->save([], [], ['logo' => UploadedFile::fake()->image('logo.png')]);

        $props = $this->props('/ar', self::HOME);

        $this->assertStringNotContainsString('/brand/', $props['store']['logo']);
        $this->assertNull($props['store']['logo_light']);
    }
}
