<?php

namespace Tests\Feature\Store;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\PageService;

class PagesAdminTest extends StorefrontTestCase
{
    private const URL = '/ar/admin/store/pages';

    public function test_index_lists_the_pages(): void
    {
        $this->actingAs($this->pageStaff())->get($this->tenantUrl(self::URL))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Page/Index')
                ->has('table.data')
                ->where('table.query.sort', 'sort_order')
                ->where('table.data', fn ($rows) => collect($rows)->pluck('title')->contains('سياسة الخصوصية')));
    }

    public function test_filters_and_search(): void
    {
        $service = app(PageService::class);
        $service->create(['slug' => 'size-guide', 'is_published' => false, 'show_in_footer' => false], ['ar' => ['title' => 'Size Guide']]);
        $this->actingAs($this->pageStaff());

        $total = fn (string $query, int $expected) => $this->get($this->tenantUrl(self::URL.'?'.$query))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', $expected));

        $total('filter[search]=size guide', 1); // another letter case
        $total('filter[is_published]=0', 1);
        $total('filter[show_in_footer]=0', 1);
        $total('filter[type]=custom', 1);
        $total('filter[type]=system', count(Page::SYSTEM));
        $total('filter[created_at_from]=not-a-date', count(Page::SYSTEM) + 1);
        $total('filter[sort_order_from]=100000', 0);
        $total('sort=-title', count(Page::SYSTEM) + 1);
    }

    public function test_create_a_page_in_the_current_locale(): void
    {
        $staff = $this->pageStaff();

        $this->actingAs($staff)->post($this->tenantUrl(self::URL), [
            'title' => 'دليل المقاسات',
            'slug' => 'size-guide',
            'body' => '<p>جدول المقاسات</p>',
            'meta_description' => 'اعرف مقاسك',
            'is_published' => true,
            'show_in_footer' => true,
            'sort_order' => 100,
        ])->assertRedirect($this->tenantUrl(self::URL));

        $page = Page::where('slug', 'size-guide')->firstOrFail();
        $this->assertSame('دليل المقاسات', $page->translate('ar')->title);
        $this->assertSame('اعرف مقاسك', $page->translate('ar')->meta_description);
        $this->assertNull($page->translate('en', false));
        $this->assertTrue($page->is_published);
        $this->assertSame($staff->id, $page->created_by);
    }

    public function test_the_body_is_sanitized_on_save(): void
    {
        $this->actingAs($this->pageStaff())->post($this->tenantUrl(self::URL), [
            'title' => 'نص',
            'slug' => 'unsafe',
            'body' => '<p>ok</p><script>alert(1)</script>',
        ])->assertRedirect();

        $this->assertStringNotContainsString('<script', Page::where('slug', 'unsafe')->firstOrFail()->translate('ar')->body);
    }

    public function test_the_english_text_is_edited_from_the_english_admin(): void
    {
        $page = app(PageService::class)->create(['slug' => 'size-guide'], ['ar' => ['title' => 'دليل المقاسات']]);
        $this->actingAs($this->pageStaff());

        // No English text yet: it starts empty, not in Arabic.
        $this->get($this->tenantUrl("/en/admin/store/pages/{$page->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia->component('Store/Page/Edit')->where('page.title', ''));

        $this->put($this->tenantUrl("/en/admin/store/pages/{$page->id}"), ['title' => 'Size guide', 'slug' => 'size-guide'])->assertRedirect();

        $page->refresh();
        $this->assertSame('Size guide', $page->translate('en')->title);
        $this->assertSame('دليل المقاسات', $page->translate('ar')->title);
    }

    public function test_a_title_is_required(): void
    {
        $this->actingAs($this->pageStaff())->post($this->tenantUrl(self::URL), ['slug' => 'x'])->assertSessionHasErrors('title');
    }

    public function test_edit_a_system_page_keeps_its_slug(): void
    {
        $privacy = app(PageRepositoryInterface::class)->findByKey('privacy');
        $this->actingAs($this->pageStaff());

        $this->get($this->tenantUrl(self::URL."/{$privacy->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.title', 'سياسة الخصوصية')
                ->where('page.slug', 'privacy-policy')
                ->where('isSystem', true));

        $this->put($this->tenantUrl(self::URL."/{$privacy->id}"), ['title' => 'خصوصيتك', 'slug' => 'changed', 'is_published' => false])
            ->assertSessionHasNoErrors();

        $privacy->refresh();
        $this->assertSame('خصوصيتك', $privacy->translate('ar')->title);
        $this->assertFalse($privacy->is_published);
        $this->assertSame('privacy-policy', $privacy->slug);
    }

    public function test_a_deleted_page_keeps_its_slug_taken(): void
    {
        $service = app(PageService::class);
        $first = $service->create(['slug' => 'offers'], ['ar' => ['title' => 'العروض']]);
        $service->delete($first);

        $second = $service->create(['slug' => 'offers'], ['ar' => ['title' => 'العروض']]);

        $this->assertSoftDeleted($first);
        $this->assertSame('offers-1', $second->slug);
    }

    public function test_custom_pages_can_be_deleted_from_the_table_but_system_pages_cannot(): void
    {
        $custom = app(PageService::class)->create(['slug' => 'temp'], ['ar' => ['title' => 'مؤقت']]);
        $privacy = app(PageRepositoryInterface::class)->findByKey('privacy');
        $this->actingAs($this->pageStaff());

        $this->delete($this->tenantUrl(self::URL."/{$privacy->id}"))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull(Page::find($privacy->id));

        $this->delete($this->tenantUrl(self::URL."/{$custom->id}"))->assertRedirect()->assertSessionHas('success');
        $this->assertNull(Page::find($custom->id));
    }

    public function test_publish_and_footer_toggles(): void
    {
        $faq = app(PageRepositoryInterface::class)->findByKey('faq');
        $this->actingAs($this->pageStaff());

        $this->put($this->tenantUrl(self::URL."/{$faq->id}/published"), ['value' => false])->assertRedirect();
        $this->put($this->tenantUrl(self::URL."/{$faq->id}/footer"), ['value' => false])->assertRedirect();

        $this->assertFalse($faq->fresh()->is_published);
        $this->assertFalse($faq->fresh()->show_in_footer);
    }

    public function test_routes_need_the_permission(): void
    {
        $privacy = app(PageRepositoryInterface::class)->findByKey('privacy');
        $clerk = $this->staff(['Product View List']);

        $this->actingAs($clerk)->get($this->tenantUrl(self::URL))->assertForbidden();
        $this->actingAs($clerk)->post($this->tenantUrl(self::URL), ['title' => 'x'])->assertForbidden();
        $this->actingAs($clerk)->put($this->tenantUrl(self::URL."/{$privacy->id}/published"), ['value' => false])->assertForbidden();
        $this->actingAs($clerk)->delete($this->tenantUrl(self::URL."/{$privacy->id}"))->assertForbidden();

        $staff = $this->pageStaff();
        $this->actingAs($staff)->get($this->tenantUrl(self::URL))->assertOk();
        $this->actingAs($staff)->get($this->tenantUrl(self::URL.'/add'))->assertOk();
        $this->actingAs($staff)->get($this->tenantUrl(self::URL."/{$privacy->id}/edit"))->assertOk();
    }

    private function pageStaff(): User
    {
        return $this->staff(['Page View List', 'Page Create', 'Page Update', 'Page Delete']);
    }
}
