<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Store\Entities\CampaignBlock;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Support\MarketingConsent;
use Tests\Feature\Store\Concerns\MakesMarketing;

/**
 * Campaign pages: built in the admin from ready blocks, each page with its own slug, publish state
 * and dates, SEO texts per language; public while published and inside its dates, a preview for
 * staff otherwise.
 */
class CampaignPagesTest extends StorefrontTestCase
{
    use MakesMarketing;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->staff = $this->staff(['Campaign Page View List', 'Campaign Page Create', 'Campaign Page Update', 'Campaign Page Delete']);
    }

    public function test_crud_filters_and_permissions(): void
    {
        $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['title' => 'تخفيضات الصيف', 'slug' => 'summer-sale']))
            ->assertSessionHasNoErrors()->assertRedirect($this->admin('/campaigns'));

        $page = CampaignPage::query()->sole();
        $this->assertSame(['summer-sale', 'published', 'تخفيضات الصيف'], [$page->slug, $page->status, $page->translate('ar')->title]);
        $this->assertSame($this->staff->id, $page->created_by);

        $draft = $this->page(['slug' => 'later', 'status' => 'draft']);

        $this->actingAs($this->staff)->get($this->admin('/campaigns'))->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
            ->component('Store/CampaignPage/Index')->where('table.meta.total', 2));
        $this->actingAs($this->staff)->get($this->admin('/campaigns?filter[status]=draft'))->assertInertia(fn (Assert $inertia) => $inertia
            ->where('table.meta.total', 1)->where('table.data.0.id', $draft->id));
        $this->actingAs($this->staff)->get($this->admin('/campaigns?filter[title]=الصيف'))->assertInertia(fn (Assert $inertia) => $inertia
            ->where('table.meta.total', 1)->where('table.data.0.id', $page->id));

        $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['slug' => 'summer-sale']))->assertSessionHasNoErrors();
        $this->assertSame('summer-sale-1', CampaignPage::query()->latest('id')->first()->slug, 'a taken slug gets a number');

        $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['title' => '']))->assertSessionHasErrors('title');
        $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['starts_at' => '2026-12-10', 'ends_at' => '2026-12-01']))->assertSessionHasErrors('ends_at');

        $this->actingAs($this->staff)->delete($this->admin('/campaigns/'.$draft->id))->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh());

        $reader = $this->staff(['Campaign Page View List']);
        $this->actingAs($reader)->get($this->admin('/campaigns/add'))->assertForbidden();
        $this->actingAs($reader)->delete($this->admin('/campaigns/'.$page->id))->assertForbidden();
    }

    public function test_blocks_are_saved_in_order_with_their_locale_texts_and_images(): void
    {
        $shirt = Product::factory()->create(['name' => 'قميص']);

        $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['blocks' => [
            ['type' => 'banner', 'settings' => ['link' => '/ar/shop'], 'content' => ['heading' => 'عنوان', 'text' => 'نص', 'button_label' => 'تسوق'], 'image' => UploadedFile::fake()->image('banner.jpg', 1200, 500)],
            ['type' => 'text', 'settings' => [], 'content' => ['html' => '<p>مرحبا</p>']],
            ['type' => 'products', 'settings' => ['source' => 'picked', 'product_ids' => [$shirt->id], 'limit' => 8], 'content' => ['heading' => 'مختارات']],
        ]]))->assertSessionHasNoErrors();

        $page = CampaignPage::query()->with('blocks')->sole();
        [$banner, $text, $products] = $page->blocks->all();
        $this->assertSame(['banner', 'text', 'products'], $page->blocks->pluck('type')->all());
        $this->assertSame([0, 1, 2], $page->blocks->pluck('sort_order')->all());
        $this->assertSame('عنوان', $banner->content['ar']['heading']);
        $this->assertSame('/ar/shop', $banner->settings['link']);
        $this->assertNotNull($banner->getFirstMedia('image'));
        $this->assertSame([$shirt->id], $products->settings['product_ids']);

        $this->actingAs($this->staff)->get($this->admin('/campaigns/'.$page->id.'/edit'))->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
            ->component('Store/CampaignPage/Edit')
            ->where('page.blocks.0.content.heading', 'عنوان')
            ->where('page.blocks.0.image', fn ($url) => is_string($url) && $url !== '')
            ->where('selectedProducts.0.value', $shirt->id));

        // In English: the other block order, the text block gone, the banner's English heading.
        $this->actingAs($this->staff)->post($this->admin('/campaigns/'.$page->id, 'en'), $this->payload([
            '_method' => 'put',
            'title' => 'Summer sale',
            'slug' => $page->slug,
            'blocks' => [
                ['id' => $products->id, 'type' => 'products', 'settings' => ['source' => 'picked', 'product_ids' => [$shirt->id], 'limit' => 4], 'content' => ['heading' => 'Picks']],
                ['id' => $banner->id, 'type' => 'banner', 'settings' => ['link' => 'https://example.com/x'], 'content' => ['heading' => 'Title', 'text' => '', 'button_label' => 'Shop']],
            ],
        ]))->assertSessionHasNoErrors();

        $page->load('blocks');
        $this->assertSame([$products->id, $banner->id], $page->blocks->modelKeys());
        $this->assertNull(CampaignBlock::query()->find($text->id));
        $this->assertSame(['عنوان', 'Title'], [$page->blocks[1]->content['ar']['heading'], $page->blocks[1]->content['en']['heading']]);
        $this->assertNotNull($page->blocks[1]->getFirstMedia('image'), 'the picture stays when none is sent');
        $this->assertSame(['تخفيضات', 'Summer sale'], [$page->translate('ar')->title, $page->translate('en')->title]);
    }

    public function test_links_and_html_are_safe(): void
    {
        $post = fn (array $block) => $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['blocks' => [$block]]));

        $post(['type' => 'cta', 'settings' => ['link' => 'javascript:alert(1)'], 'content' => ['heading' => 'x', 'button_label' => 'Go']])->assertSessionHasErrors('blocks.0.settings.link');
        $post(['type' => 'cta', 'settings' => ['link' => '//evil.example'], 'content' => ['heading' => 'x', 'button_label' => 'Go']])->assertSessionHasErrors('blocks.0.settings.link');
        $post(['type' => 'cta', 'settings' => ['link' => '/ar/shop'], 'content' => ['heading' => 'x']])->assertSessionHasErrors('blocks.0.content.button_label');
        $post(['type' => 'banner', 'settings' => [], 'content' => ['heading' => 'x']])->assertSessionHasErrors('blocks.0.image');
        $post(['type' => 'countdown', 'settings' => [], 'content' => ['heading' => 'x']])->assertSessionHasErrors('blocks.0.settings.ends_at');
        $post(['type' => 'products', 'settings' => ['source' => 'category'], 'content' => []])->assertSessionHasErrors('blocks.0.settings.category_id');
        $post(['type' => 'bogus', 'settings' => [], 'content' => []])->assertSessionHasErrors('blocks.0.type');
        $this->assertSame(0, CampaignPage::query()->count());

        $post(['type' => 'text', 'settings' => [], 'content' => ['html' => '<p onclick="x()">مرحبا</p><script>alert(1)</script>']])->assertSessionHasNoErrors();
        $html = CampaignBlock::query()->sole()->content['ar']['html'];
        $this->assertStringContainsString('مرحبا', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_a_link_is_a_site_path_or_a_web_address_only(): void
    {
        $post = fn (string $link) => $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['blocks' => [
            ['type' => 'cta', 'settings' => ['link' => $link], 'content' => ['heading' => 'x', 'button_label' => 'Go']],
        ]]));

        foreach (['/\\evil.example', '/\\/evil.example', '\\\\evil.example', 'ftp://example.com'] as $link) {
            $post($link)->assertSessionHasErrors('blocks.0.settings.link');
        }

        $this->assertSame(0, CampaignPage::query()->count());
        $post('/ar/shop?sort=newest')->assertSessionHasNoErrors();
        $post('https://example.com/a')->assertSessionHasNoErrors();
    }

    public function test_a_block_whose_type_changed_needs_its_own_picture(): void
    {
        $this->actingAs($this->staff)->post($this->admin('/campaigns'), $this->payload(['blocks' => [
            ['type' => 'banner', 'settings' => [], 'content' => ['heading' => 'x'], 'image' => UploadedFile::fake()->image('banner.jpg', 800, 400)],
        ]]))->assertSessionHasNoErrors();
        $page = CampaignPage::query()->with('blocks')->sole();
        $banner = $page->blocks->sole();

        $update = fn (array $block) => $this->actingAs($this->staff)->post($this->admin('/campaigns/'.$page->id), $this->payload(['_method' => 'put', 'slug' => $page->slug, 'blocks' => [$block]]));

        $update(['id' => $banner->id, 'type' => 'image', 'settings' => [], 'content' => ['alt' => 'x']])->assertSessionHasErrors('blocks.0.image');
        $update(['id' => $banner->id, 'type' => 'banner', 'settings' => [], 'content' => ['heading' => 'y']])->assertSessionHasNoErrors();
    }

    public function test_a_published_page_in_its_window_is_public(): void
    {
        $page = $this->page(['slug' => 'summer-sale', 'starts_at' => Carbon::now()->subDay(), 'ends_at' => Carbon::now()->addDay()], 'تخفيضات', [
            ['type' => 'text', 'content' => ['ar' => ['html' => '<p>مرحبا</p>']]],
            ['type' => 'cta', 'settings' => ['link' => '/ar/shop'], 'content' => ['ar' => ['heading' => 'تسوق الآن', 'button_label' => 'اذهب']]],
            ['type' => 'countdown', 'settings' => ['ends_at' => Carbon::now()->addHours(5)->toIso8601String()], 'content' => ['ar' => ['heading' => 'ينتهي قريبًا']]],
            ['type' => 'countdown', 'settings' => ['ends_at' => Carbon::now()->subHour()->toIso8601String()], 'content' => ['ar' => ['heading' => 'انتهى']]],
        ], seo: ['seo_title' => 'عنوان البحث', 'seo_description' => 'وصف البحث']);

        $props = $this->props('/ar/campaign/summer-sale', 'Store/Storefront/Campaign');

        $this->assertSame('تخفيضات', $props['campaign']['title']);
        $this->assertFalse($props['campaign']['preview']);
        $this->assertSame(['text', 'cta', 'countdown'], array_column($props['blocks'], 'type'), 'a countdown that ended is left out');
        $this->assertSame('<p>مرحبا</p>', $props['blocks'][0]['html']);
        $this->assertSame(['تسوق الآن', 'اذهب', '/ar/shop'], [$props['blocks'][1]['heading'], $props['blocks'][1]['button_label'], $props['blocks'][1]['link']]);
        $this->assertGreaterThan(17000, $props['blocks'][2]['seconds_left']);
        $this->assertStringStartsWith('عنوان البحث', $props['seo']['title']);
        $this->assertSame('وصف البحث', $props['seo']['description']);
        $this->assertFalse($props['seo']['noindex']);

        // English falls back to the Arabic texts where none were written.
        $this->assertSame('تخفيضات', $this->props('/en/campaign/summer-sale', 'Store/Storefront/Campaign')['campaign']['title']);
        $this->assertNotNull($page);
    }

    public function test_drafts_and_closed_windows_are_404_but_previewed_by_staff(): void
    {
        $this->page(['slug' => 'draft', 'status' => 'draft']);
        $this->page(['slug' => 'over', 'ends_at' => Carbon::now()->subDay()]);
        $this->page(['slug' => 'soon', 'starts_at' => Carbon::now()->addDay()]);

        foreach (['draft', 'over', 'soon', 'missing'] as $slug) {
            $this->get($this->siteUrl('/ar/campaign/'.$slug))->assertNotFound();
        }

        $customer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer-'.uniqid().'@store.test', 'password' => 'secret-password']);
        $this->actingAs($customer)->get($this->siteUrl('/ar/campaign/draft'))->assertNotFound();

        $preview = $this->actingAs($this->staff)->props('/ar/campaign/draft', 'Store/Storefront/Campaign');
        $this->assertTrue($preview['campaign']['preview']);
        $this->assertTrue($preview['seo']['noindex']);
    }

    public function test_product_grid_from_picked_products_or_a_category(): void
    {
        $category = Category::factory()->create(['slug' => 'shirts']);
        $first = Product::factory()->create(['name' => 'أول']);
        $second = Product::factory()->create(['name' => 'ثاني']);
        $hidden = Product::factory()->create(['name' => 'مسودة', 'status' => ProductStatus::DRAFT]);
        $inCategory = Product::factory()->create(['name' => 'قميص']);
        $inCategory->categories()->attach($category->id);

        $this->page(['slug' => 'grid'], 'شبكة', [
            ['type' => 'products', 'settings' => ['source' => 'picked', 'product_ids' => [$second->id, $hidden->id, $first->id], 'limit' => 8], 'content' => ['ar' => ['heading' => 'مختارات']]],
            ['type' => 'products', 'settings' => ['source' => 'category', 'category_id' => $category->id, 'limit' => 4], 'content' => []],
        ]);

        $blocks = $this->props('/ar/campaign/grid', 'Store/Storefront/Campaign')['blocks'];

        $this->assertSame(['ثاني', 'أول'], array_column($blocks[0]['cards'], 'name'));
        $this->assertSame('مختارات', $blocks[0]['heading']);
        $this->assertSame(['قميص'], array_column($blocks[1]['cards'], 'name'));
        $this->assertSame(route('store.category', ['slug' => 'shirts']), $blocks[1]['more_url']);
    }

    public function test_the_sitemap_lists_live_campaigns(): void
    {
        $this->page(['slug' => 'live-one']);
        $this->page(['slug' => 'draft-one', 'status' => 'draft']);

        $xml = (string) $this->get($this->siteUrl('/sitemap.xml'))->assertOk()->getContent();

        $this->assertStringContainsString('/ar/campaign/live-one', $xml);
        $this->assertStringNotContainsString('draft-one', $xml);
    }

    public function test_page_view_is_built_but_not_in_preview(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $this->marketing();
        $this->page(['slug' => 'live']);
        $this->page(['slug' => 'draft', 'status' => 'draft']);

        $live = $this->withCookie(MarketingConsent::COOKIE, 'granted')->props('/ar/campaign/live', 'Store/Storefront/Campaign')['marketing'];
        $this->assertSame(['PageView'], array_column($live['events'], 'name'));

        $preview = $this->actingAs($this->staff)->withCookie(MarketingConsent::COOKIE, 'granted')->props('/ar/campaign/draft', 'Store/Storefront/Campaign');
        $this->assertNull($preview['marketing']);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, string>  $seo
     */
    private function page(array $attributes = [], string $title = 'حملة', array $blocks = [], array $seo = []): CampaignPage
    {
        $page = new CampaignPage(['slug' => 'c-'.uniqid(), 'status' => 'published', ...$attributes]);
        $page->translateOrNew('ar')->fill(['title' => $title, ...$seo]);
        $page->save();

        foreach ($blocks as $index => $block) {
            $page->blocks()->create(['type' => $block['type'], 'sort_order' => $index, 'settings' => $block['settings'] ?? [], 'content' => $block['content'] ?? []]);
        }

        return $page;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'تخفيضات',
            'seo_title' => null,
            'seo_description' => null,
            'slug' => '',
            'status' => 'published',
            'starts_at' => null,
            'ends_at' => null,
            'blocks' => [],
            ...$overrides,
        ];
    }

    private function admin(string $path, string $locale = 'ar'): string
    {
        return $this->siteUrl('/'.$locale.'/admin/store'.$path);
    }
}
