<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\Branch;
use Modules\Items\Entities\Product;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\PageService;
use Modules\Store\Services\StoreAppearanceService;

class InfoPagesTest extends StorefrontTestCase
{
    public function test_a_policy_page_renders_with_the_store_details(): void
    {
        $this->siteName('Maison');
        app(StoreAppearanceService::class)->save([], ['whatsapp' => '970599000000'], []);

        $props = $this->props('/ar/page/return-policy', 'Store/Storefront/PageView');

        $this->assertSame('سياسة الاستبدال والاسترجاع', $props['page']['title']);
        $this->assertStringContainsString('Maison', $props['body']);
        $this->assertStringContainsString('+970599000000', $props['body']);
        $this->assertStringNotContainsString('{store}', $props['body']);

        $this->assertSame('Returns & refunds', $this->props('/en/page/return-policy', 'Store/Storefront/PageView')['page']['title']);
    }

    public function test_rich_text_passes_through_rich_text_before_it_is_shown(): void
    {
        $page = app(PageService::class)->create(['slug' => 'clean'], ['ar' => ['title' => 'نظيفة', 'body' => '<p>ok</p>']]);

        // A row written by hand, around the service.
        DB::table('store_page_translations')->where('store_page_id', $page->id)
            ->update(['body' => '<p>نص</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">رابط</a>']);

        $body = $this->props('/ar/page/clean', 'Store/Storefront/PageView')['body'];

        $this->assertStringContainsString('<p>نص</p>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onclick', $body);
    }

    public function test_unpublished_or_unknown_pages_are_not_found(): void
    {
        $pages = app(PageRepositoryInterface::class);
        app(PageService::class)->setPublished($pages->findByKey('terms'), false);

        $this->get($this->siteUrl('/ar/page/terms'))->assertNotFound();
        $this->get($this->siteUrl('/ar/page/nothing'))->assertNotFound();
    }

    public function test_footer_lists_published_footer_pages_and_contact(): void
    {
        $service = app(PageService::class);
        $service->create(['slug' => 'size-guide', 'show_in_footer' => true], ['ar' => ['title' => 'دليل المقاسات']]);
        $service->create(['slug' => 'secret', 'show_in_footer' => false], ['ar' => ['title' => 'صفحة سرية']]);
        $service->setPublished(app(PageRepositoryInterface::class)->findByKey('faq'), false);

        $store = $this->props('/ar', 'Store/Storefront/Home')['store'];
        $titles = array_column([...$store['footer_pages'], ...$store['policy_pages']], 'title');
        $slugs = array_column($store['footer_pages'], 'slug');

        $this->assertContains('سياسة الخصوصية', $titles);
        $this->assertContains('دليل المقاسات', $titles);
        $this->assertNotContains('صفحة سرية', $titles);
        $this->assertNotContains('faq', $slugs);
    }

    public function test_contact_page_shows_only_what_the_store_set(): void
    {
        app(StoreAppearanceService::class)->save([], ['phone' => '022950000', 'email' => 'hi@maison.test', 'instagram_url' => 'https://instagram.com/maison'], []);
        Branch::factory()->create();

        $props = $this->props('/ar/contact', 'Store/Storefront/Contact');

        $this->assertSame('022950000', $props['channels']['phone']);
        $this->assertSame('hi@maison.test', $props['channels']['email']);
        $this->assertArrayNotHasKey('whatsapp', $props['channels']);
        $this->assertSame('https://instagram.com/maison', $props['socials'][0]['url']);
        $this->assertNotSame([], $props['branches']);
    }

    public function test_an_empty_contact_page_has_no_channels_or_branches(): void
    {
        $props = $this->props('/ar/contact', 'Store/Storefront/Contact');

        $this->assertNull($props['channels']);
        $this->assertSame([], $props['socials']);
        $this->assertSame([], $props['branches']);
    }

    public function test_product_page_trust_row_links_to_published_policies(): void
    {
        Product::factory()->create(['slug' => 'mug', 'manage_stock' => 0]);
        app(PageService::class)->setPublished(app(PageRepositoryInterface::class)->findByKey('returns'), false);

        $props = $this->props('/ar/product/mug', 'Store/Storefront/ProductPage');

        $this->assertStringEndsWith('/ar/page/shipping-policy', $props['policies']['shipping']);
        $this->assertArrayNotHasKey('returns', $props['policies']);
        $this->assertFalse($props['hasWhatsapp']);
    }

    public function test_floating_whatsapp_button_only_with_a_number(): void
    {
        $this->assertNull($this->props('/ar', 'Store/Storefront/Home')['store']['whatsapp']);

        app(StoreAppearanceService::class)->save([], ['whatsapp' => '970599000000'], []);

        $this->assertSame('970599000000', $this->props('/ar', 'Store/Storefront/Home')['store']['whatsapp']);
    }
}
