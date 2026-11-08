<?php

namespace Tests\Feature\Store;

use InvalidArgumentException;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\PageService;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Support\PagePlaceholders;

class StorePagesTest extends StorefrontTestCase
{
    public function test_system_pages_exist_in_both_locales(): void
    {
        $pages = app(PageRepositoryInterface::class);

        foreach (Page::SYSTEM as $key) {
            $page = $pages->findByKey($key);
            $this->assertNotNull($page, "{$key} page");
            $this->assertTrue($page->is_published);
            $this->assertNotEmpty($page->translate('ar')?->title);
            $this->assertNotEmpty($page->translate('en')?->title);
            $this->assertStringContainsString('<', (string) $page->translate('ar')->body);
        }
    }

    public function test_create_update_and_unique_slug(): void
    {
        $service = app(PageService::class);

        $page = $service->create(
            ['slug' => 'Size Guide', 'is_published' => true, 'show_in_footer' => true, 'sort_order' => 50],
            ['ar' => ['title' => 'دليل المقاسات', 'body' => '<p>نص</p>'], 'en' => ['title' => 'Size guide', 'body' => '<p>Text</p>']],
        );
        $this->assertSame('size-guide', $page->slug);
        $this->assertNull($page->key);

        $second = $service->create(['slug' => 'size-guide'], ['ar' => ['title' => 'مكرر']]);
        $this->assertSame('size-guide-1', $second->slug);

        $service->update($page, ['sort_order' => 5], ['en' => ['title' => 'Sizes']]);
        $this->assertSame('Sizes', $page->fresh()->translate('en')->title);
        $this->assertSame('دليل المقاسات', $page->fresh()->translate('ar')->title);
    }

    public function test_body_is_sanitized_on_save(): void
    {
        $page = app(PageService::class)->create(['slug' => 'x'], ['ar' => ['title' => 'س', 'body' => '<p>ok</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>']]);

        $body = $page->fresh()->translate('ar')->body;
        $this->assertStringContainsString('<p>ok</p>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_system_pages_cannot_be_deleted_or_renamed(): void
    {
        $service = app(PageService::class);
        $privacy = app(PageRepositoryInterface::class)->findByKey('privacy');

        $service->update($privacy, ['slug' => 'renamed'], []);
        $this->assertSame('privacy-policy', $privacy->fresh()->slug);

        $this->expectException(InvalidArgumentException::class);
        $service->delete($privacy);
    }

    public function test_custom_pages_can_be_deleted(): void
    {
        $service = app(PageService::class);
        $page = $service->create(['slug' => 'temp'], ['ar' => ['title' => 'مؤقت']]);

        $service->delete($page);

        $this->assertNull(Page::find($page->id));
    }

    public function test_footer_pages_and_published_lookup(): void
    {
        $pages = app(PageRepositoryInterface::class);
        $service = app(PageService::class);
        $service->create(['slug' => 'hidden', 'is_published' => false, 'show_in_footer' => true], ['ar' => ['title' => 'مخفي']]);
        $notInFooter = $service->create(['slug' => 'extra', 'is_published' => true, 'show_in_footer' => false], ['ar' => ['title' => 'إضافي']]);

        $footer = $pages->footerPages()->pluck('slug')->all();
        $this->assertContains('privacy-policy', $footer);
        $this->assertNotContains('hidden', $footer);
        $this->assertNotContains('extra', $footer);

        $this->assertNull($pages->findPublishedBySlug('hidden'));
        $this->assertSame($notInFooter->id, $pages->findPublishedBySlug('extra')?->id);

        $service->setPublished($pages->findByKey('faq'), false);
        $this->assertNotContains('faq', $pages->publishedKeys());
    }

    public function test_placeholders_are_filled_from_the_store_settings(): void
    {
        $this->siteName('Maison');
        app(StoreAppearanceService::class)->save([], ['email' => 'hi@maison.test', 'phone' => '022950000', 'whatsapp' => '970599000000'], []);

        $this->assertSame(
            'Maison: hi@maison.test, 022950000, +970599000000',
            PagePlaceholders::fill('{store}: {email}, {phone}, {whatsapp}'),
        );
    }

    public function test_missing_contact_placeholders_become_a_generic_phrase(): void
    {
        $this->assertStringNotContainsString('{email}', PagePlaceholders::fill('{email}'));
    }
}
