<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\BranchStatus;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Entities\ContactMessage;
use Modules\Store\Entities\Member;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\ContactMessageService;
use Modules\Store\Services\MemberService;
use Modules\Store\Services\PageService;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Theme\BrandColor;
use Tests\DatabaseTestCase;

/**
 * The storefront API for the mobile app: the store's look, home texts, contact details, pages
 * and the two forms, all from what the owner set on the dashboard.
 */
class StoreApiTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('store-contact:testing:127.0.0.1');
        RateLimiter::clear('store-members:testing:127.0.0.1');
    }

    public function test_appearance_has_the_dashboard_color_shades_font_and_logo(): void
    {
        $this->siteName('Maison Test');
        app(StoreAppearanceService::class)->save([
            'primary_color' => '#6b2d5c',
            'font' => 'cairo',
            'announcement' => ['ar' => 'توصيل مجاني', 'en' => 'Free delivery'],
        ], [], []);

        $response = $this->getJson($this->siteUrl('/api/v1/store/appearance'), ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Maison Test')
            ->assertJsonPath('data.color', '#6B2D5C')
            ->assertJsonPath('data.on_color', '#FFFFFF')
            ->assertJsonPath('data.font', ['key' => 'cairo', 'name' => 'Cairo'])
            ->assertJsonPath('data.announcement', 'Free delivery')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.rtl', false)
            ->assertJsonPath('data.logo', null);

        $this->assertSame(BrandColor::fromHex('#6B2D5C')->shades(), array_combine(
            array_map('intval', array_keys($response->json('data.shades'))),
            array_values($response->json('data.shades')),
        ));
    }

    public function test_appearance_version_changes_with_the_theme(): void
    {
        $before = $this->getJson($this->siteUrl('/api/v1/store/appearance'))->assertOk()->json('data.version');
        $this->assertSame($before, $this->getJson($this->siteUrl('/api/v1/store/appearance'))->json('data.version'));

        app(StoreAppearanceService::class)->save(['primary_color' => '#777777'], [], []);

        $after = $this->getJson($this->siteUrl('/api/v1/store/appearance'))
            ->assertJsonPath('data.color', '#777777')
            ->assertJsonPath('data.on_color', BrandColor::DARK_TEXT)
            ->json('data.version');
        $this->assertNotSame($before, $after);
    }

    public function test_appearance_follows_a_phone_accept_language_header(): void
    {
        app(StoreAppearanceService::class)->save(['announcement' => ['ar' => 'توصيل مجاني', 'en' => 'Free delivery']], [], []);

        $this->getJson($this->siteUrl('/api/v1/store/appearance'), ['Accept-Language' => 'ar-EG,ar;q=0.9,en;q=0.8'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.rtl', true)
            ->assertJsonPath('data.announcement', 'توصيل مجاني');
    }

    public function test_home_has_the_owner_texts_promises_and_membership(): void
    {
        app(StoreAppearanceService::class)->save([
            'hero_title' => ['ar' => 'أناقة كل يوم', 'en' => 'Everyday elegance'],
            'story_title' => ['ar' => 'حكايتنا', 'en' => 'Our story'],
            'story_text' => ['ar' => 'بدأنا من محل صغير', 'en' => 'We started small'],
            'values' => [['title' => ['ar' => 'جودة', 'en' => 'Quality'], 'text' => ['ar' => 'خامات أصلية', 'en' => '']]],
        ], ['whatsapp' => '970599000000'], []);
        app(PageService::class)->setPublished(app(PageRepositoryInterface::class)->findByKey('shipping'), false);

        $response = $this->getJson($this->siteUrl('/api/v1/store/home'), ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.hero.title', 'أناقة كل يوم')
            ->assertJsonPath('data.story.text', 'بدأنا من محل صغير')
            ->assertJsonPath('data.story.about', app(PageRepositoryInterface::class)->findByKey('about')->slug)
            ->assertJsonPath('data.values.0', ['title' => 'جودة', 'text' => 'خامات أصلية']);

        $promises = collect($response->json('data.promises'))->keyBy('key');
        $this->assertSame(['returns', 'whatsapp', 'privacy'], $promises->keys()->all());
        $this->assertNull($promises['whatsapp']['page']);
        $this->assertSame('الدفع عند الاستلام', $promises['whatsapp']['title']);
        $this->assertNotNull($response->json('data.membership.title'));

        app(StoreAppearanceService::class)->save(['membership_enabled' => false], [], []);
        $this->getJson($this->siteUrl('/api/v1/store/home'))->assertOk()->assertJsonPath('data.membership', null);
    }

    public function test_contact_has_only_what_the_store_set_and_open_branches(): void
    {
        app(StoreAppearanceService::class)->save([], [
            'phone' => '022950000',
            'whatsapp' => '+970 599 000 000',
            'address' => ['ar' => 'نابلس، شارع فيصل', 'en' => 'Nablus, Faisal St'],
            'instagram_url' => 'https://instagram.com/maison',
        ], []);
        $open = Branch::factory()->create(['opening_time' => '09:00:00', 'closing_time' => '23:00:00']);
        $closed = Branch::factory()->create(['status' => BranchStatus::CLOSED]);

        $response = $this->getJson($this->siteUrl('/api/v1/store/contact'), ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.phone', '022950000')
            ->assertJsonPath('data.whatsapp', '970599000000')
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.address', 'نابلس، شارع فيصل')
            ->assertJsonPath('data.socials', [['network' => 'instagram', 'url' => 'https://instagram.com/maison']])
            ->assertJsonPath('data.topics.2', ['key' => 'delivery', 'label' => 'التوصيل']);

        $branches = collect($response->json('data.branches'))->keyBy('id');
        $this->assertTrue($branches->has($open->id));
        $this->assertFalse($branches->has($closed->id));
        $this->assertSame('09:00–23:00', $branches[$open->id]['hours']);
    }

    public function test_pages_list_and_page_with_faq_questions(): void
    {
        $pages = app(PageRepositoryInterface::class);
        app(PageService::class)->setPublished($pages->findByKey('terms'), false);

        $list = collect($this->getJson($this->siteUrl('/api/v1/store/pages'))->assertOk()->json('data'))->keyBy('key');
        $this->assertTrue($list->has('faq'));
        $this->assertFalse($list->has('terms'));
        $this->assertSame('policies', $list['shipping']['group']);
        $this->assertSame('about', $list['faq']['group']);

        $faq = $this->getJson($this->siteUrl('/api/v1/store/pages/'.$pages->findByKey('faq')->slug))
            ->assertOk()
            ->assertJsonPath('data.key', 'faq');
        $this->assertNotEmpty($faq->json('data.faq.items'));
        $this->assertNotEmpty($faq->json('data.faq.items.0.question'));
        $this->assertStringNotContainsString('{store}', $faq->json('data.body'));

        $this->getJson($this->siteUrl('/api/v1/store/pages/'.$pages->findByKey('terms')->slug))
            ->assertNotFound()
            ->assertJsonPath('status', false);
        $this->getJson($this->siteUrl('/api/v1/store/pages/no-such-page'))->assertNotFound();
    }

    public function test_a_buyer_sends_a_message_from_the_app(): void
    {
        $this->postJson($this->siteUrl('/api/v1/store/contact-messages'), [
            'name' => '  سارة  ',
            'phone' => '0599 123 456',
            'topic' => 'delivery',
            'message' => 'متى يصل طلبي إلى نابلس؟',
        ], ['Accept-Language' => 'ar'])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'شكراً لك، وصلتنا رسالتك.');

        $message = ContactMessage::sole();
        $this->assertSame('سارة', $message->name);
        $this->assertSame('التوصيل', $message->subject);
        $this->assertSame('ar', $message->locale);
    }

    public function test_message_validation_errors_come_per_field_in_the_app_language(): void
    {
        $this->postJson($this->siteUrl('/api/v1/store/contact-messages'), ['name' => 'سارة', 'message' => 'قصير'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.phone.0', 'اترك رقم هاتف أو بريداً إلكترونياً لنتمكن من الرد عليك.')
            ->assertJsonStructure(['data' => ['message']]);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_messages_are_limited_per_visitor(): void
    {
        $payload = ['name' => 'سارة', 'email' => 'sara@example.com', 'message' => 'سؤال عن المقاسات المتوفرة'];

        for ($i = 0; $i < ContactMessageService::MAX_PER_WINDOW; $i++) {
            $this->postJson($this->siteUrl('/api/v1/store/contact-messages'), $payload)->assertCreated();
        }

        $this->postJson($this->siteUrl('/api/v1/store/contact-messages'), $payload)
            ->assertStatus(429)
            ->assertJsonPath('status', false);
        $this->assertSame(ContactMessageService::MAX_PER_WINDOW, ContactMessage::count());
    }

    public function test_a_buyer_joins_the_members_once_per_phone(): void
    {
        $this->postJson($this->siteUrl('/api/v1/store/members'), ['name' => 'سارة', 'phone' => '0599 123 456'])
            ->assertCreated()
            ->assertJsonPath('data.new', true);

        $this->postJson($this->siteUrl('/api/v1/store/members'), ['name' => 'سارة أحمد', 'phone' => '0599123456', 'email' => 'sara@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.new', false);

        $member = Member::sole();
        $this->assertSame('0599123456', $member->phone);
        $this->assertSame('سارة أحمد', $member->name);
        $this->assertSame('sara@example.com', $member->email);
    }

    public function test_members_are_refused_while_the_store_does_not_take_them_or_too_often(): void
    {
        app(StoreAppearanceService::class)->save(['membership_enabled' => false], [], []);
        $this->postJson($this->siteUrl('/api/v1/store/members'), ['name' => 'سارة', 'phone' => '0599123456'])
            ->assertForbidden()
            ->assertJsonPath('status', false);

        app(StoreAppearanceService::class)->save(['membership_enabled' => true], [], []);
        for ($i = 0; $i < MemberService::MAX_PER_WINDOW; $i++) {
            $this->postJson($this->siteUrl('/api/v1/store/members'), ['name' => 'سارة', 'phone' => '059912345'.$i])->assertCreated();
        }
        $this->postJson($this->siteUrl('/api/v1/store/members'), ['name' => 'سارة', 'phone' => '0599999999'])->assertStatus(429);
        $this->assertSame(MemberService::MAX_PER_WINDOW, Member::count());
    }

    public function test_other_http_errors_keep_their_code_in_the_api_envelope(): void
    {
        $this->putJson($this->siteUrl('/api/v1/store/appearance'))
            ->assertStatus(405)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data', null);
    }

    private function siteName(string $name): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = $name;
        $settings->save();
    }
}
