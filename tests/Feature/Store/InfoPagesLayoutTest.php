<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\PageService;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Support\PageOutline;
use Modules\Store\Support\PagePlaceholders;

/**
 * "About us", the {contact} placeholder, the FAQ accordion, the "On this page" menu, the help menu
 * and the footer's store/policies columns.
 */
class InfoPagesLayoutTest extends StorefrontTestCase
{
    public function test_about_page_exists_and_shows_the_store_story_and_values(): void
    {
        $this->siteName('Maison');
        app(StoreAppearanceService::class)->save([
            'values' => [['title' => ['ar' => 'الجودة أولاً', 'en' => 'Quality first'], 'text' => ['ar' => 'نختار بعناية', 'en' => 'Chosen with care']]],
        ], [], []);

        $about = app(PageRepositoryInterface::class)->findByKey('about');
        $this->assertNotNull($about);
        $this->assertSame('about-us', $about->slug);

        $props = $this->props('/ar/page/about-us', 'Store/Storefront/About');
        $this->assertSame('من نحن', $props['page']['title']);
        $this->assertStringContainsString('أهلاً بك في Maison', $props['body']);
        $this->assertStringNotContainsString('{contact}', $props['body']);
        $this->assertSame('الجودة أولاً', $props['values'][0]['title']);

        $this->assertStringContainsString('Welcome to Maison', $this->props('/en/page/about-us', 'Store/Storefront/About')['body']);
    }

    public function test_header_links_to_the_about_page_or_the_home_story(): void
    {
        $url = fn () => $this->props('/ar', 'Store/Storefront/Home')['store']['about_url'];

        $this->assertSame($this->siteUrl('/ar/page/about-us'), $url());

        app(PageService::class)->setPublished(app(PageRepositoryInterface::class)->findByKey('about'), false);
        $this->assertNull($url());

        app(StoreAppearanceService::class)->save(['story_text' => ['ar' => 'بدأنا في رام الله']], [], []);
        $this->assertStringEndsWith('/ar#story', $url());
    }

    public function test_contact_placeholder_lists_only_the_channels_the_store_set(): void
    {
        $this->assertStringContainsString('صفحة تواصل معنا', PagePlaceholders::fill('{contact}'));
        $this->assertStringContainsString('/ar/contact', PagePlaceholders::fill('{contact}'));

        app(StoreAppearanceService::class)->save([], ['whatsapp' => '970599000000'], []);
        $this->assertSame(
            'واتساب <a href="https://wa.me/970599000000" dir="ltr">+970599000000</a>',
            PagePlaceholders::fill('{contact}'),
        );

        app(StoreAppearanceService::class)->save([], ['whatsapp' => '970599000000', 'phone' => '022950000', 'email' => 'hi@maison.test'], []);
        $filled = PagePlaceholders::fill('{contact}');
        $this->assertStringContainsString('، الهاتف <a href="tel:022950000"', $filled);
        $this->assertStringContainsString(' أو البريد <a href="mailto:hi@maison.test">', $filled);
    }

    public function test_starter_texts_no_longer_read_our_contact_channels(): void
    {
        $this->assertStringNotContainsString('قنوات التواصل لدينا', json_encode($this->props('/ar/page/faq', 'Store/Storefront/PageView')['faq'], JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('قنوات التواصل لدينا', $this->props('/ar/page/shipping-policy', 'Store/Storefront/PageView')['body']);
    }

    public function test_the_migration_rewrites_untouched_starter_sentences_only(): void
    {
        $faq = app(PageRepositoryInterface::class)->findByKey('faq');
        DB::table('store_page_translations')->where('store_page_id', $faq->id)->where('locale', 'ar')
            ->update(['body' => '<p>واتساب {whatsapp}، هاتف {phone}، بريد {email}.</p><p>جملتي {whatsapp}</p>']);

        $migration = require base_path('Modules/Store/database/migrations/2026_10_28_100000_add_about_page_and_contact_placeholder.php');
        $migration->up();

        $this->assertSame(
            '<p>يسعدنا تواصلك عبر {contact}.</p><p>جملتي {whatsapp}</p>',
            DB::table('store_page_translations')->where('store_page_id', $faq->id)->where('locale', 'ar')->value('body'),
        );
        $this->assertSame(1, DB::table('store_pages')->where('key', 'about')->count());
    }

    public function test_faq_shows_questions_that_open_and_tells_search_engines(): void
    {
        $props = $this->props('/ar/page/faq', 'Store/Storefront/PageView');

        $this->assertContains('كيف أطلب؟', array_column($props['faq']['items'], 'question'));
        $this->assertSame('FAQPage', json_decode($props['seo']['json_ld'][0], true)['@type']);
    }

    public function test_long_pages_get_an_on_this_page_menu_and_every_page_a_help_menu(): void
    {
        $props = $this->props('/ar/page/privacy-policy', 'Store/Storefront/PageView');

        $this->assertSame('section-1', $props['toc'][0]['id']);
        $this->assertStringContainsString('id="section-1"', $props['body']);
        $this->assertContains('terms', array_column($props['help']['policies'], 'slug'));
        $this->assertNotContains('privacy-policy', array_column($props['help']['policies'], 'slug'));
        $this->assertContains('about-us', array_column($props['help']['about'], 'slug'));

        $short = app(PageService::class)->create(['slug' => 'short'], ['ar' => ['title' => 'قصيرة', 'body' => '<h2>واحد</h2><p>نص</p>']]);
        $this->assertSame([], $this->props('/ar/page/'.$short->slug, 'Store/Storefront/PageView')['toc']);
    }

    public function test_footer_splits_store_pages_and_policies(): void
    {
        $store = $this->props('/ar', 'Store/Storefront/Home')['store'];

        $this->assertContains('about-us', array_column($store['footer_pages'], 'slug'));
        $this->assertContains('faq', array_column($store['footer_pages'], 'slug'));
        $this->assertContains('privacy-policy', array_column($store['policy_pages'], 'slug'));
        $this->assertNotContains('about-us', array_column($store['policy_pages'], 'slug'));
    }

    public function test_outline_reads_sections_and_questions(): void
    {
        $sections = PageOutline::sections('<p>مقدمة</p><h2>أولاً</h2><p>أ</p><h2>ثانياً</h2>');
        $this->assertSame([['id' => 'section-1', 'title' => 'أولاً'], ['id' => 'section-2', 'title' => 'ثانياً']], $sections['toc']);
        $this->assertStringContainsString('<h2 id="section-1">أولاً</h2>', $sections['html']);

        $questions = PageOutline::questions('<p>مقدمة</p><h2>سؤال؟</h2><p>جواب</p><ul><li>بند</li></ul><h2>آخر؟</h2>');
        $this->assertSame('<p>مقدمة</p>', $questions['intro']);
        $this->assertSame('سؤال؟', $questions['items'][0]['question']);
        $this->assertSame('<p>جواب</p><ul><li>بند</li></ul>', $questions['items'][0]['answer']);
        $this->assertSame('', $questions['items'][1]['answer']);
    }
}
