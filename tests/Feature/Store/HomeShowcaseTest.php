<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Member;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\MemberService;
use Modules\Store\Services\PageService;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Support\Price;

/**
 * The luxury home page: the signature piece, the promises, and the members circle with the admin
 * list it fills.
 */
class HomeShowcaseTest extends StorefrontTestCase
{
    private const HOME = 'Store/Storefront/Home';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('store-members:testing:127.0.0.1');
    }

    public function test_the_first_pick_is_the_signature_piece_and_the_rest_fill_the_grid(): void
    {
        Product::factory()->create(['name' => 'ساعة ذهبية', 'base_price' => 500, 'sale_price' => 400, 'is_featured' => 1, 'short_description' => 'صناعة يدوية']);
        Product::factory()->create(['name' => 'حقيبة جلد', 'is_featured' => 0]);

        $props = $this->props('/ar', self::HOME);

        $this->assertSame('ساعة ذهبية', $props['signature']['name']);
        $this->assertSame('صناعة يدوية', $props['signature']['summary']);
        $this->assertSame(20, $props['signature']['price']['percent_off']);
        $this->assertSame(['حقيبة جلد'], array_column($props['featured'], 'name'));
    }

    public function test_one_product_shows_only_the_signature_piece(): void
    {
        Product::factory()->create(['name' => 'ساعة ذهبية']);

        $props = $this->props('/ar', self::HOME);

        $this->assertSame('ساعة ذهبية', $props['signature']['name']);
        $this->assertSame([], $props['featured']);
        $this->assertNull($props['heroPiece']);
    }

    public function test_promises_follow_the_published_pages_and_whatsapp(): void
    {
        $keys = fn () => array_column($this->props('/ar', self::HOME)['promises'], 'key');

        $this->assertSame(['shipping', 'returns', 'privacy'], $keys());

        app(PageService::class)->setPublished(app(PageRepositoryInterface::class)->findByKey('shipping'), false);
        app(StoreAppearanceService::class)->save([], ['whatsapp' => '970599000000'], []);

        $this->assertSame(['returns', 'whatsapp', 'privacy'], $keys());
        $this->assertSame('الدفع عند الاستلام', collect($this->props('/ar', self::HOME)['promises'])->firstWhere('key', 'whatsapp')['title']);
    }

    public function test_members_section_is_on_by_default_and_follows_the_owner_settings(): void
    {
        $this->siteName('Maison');

        $this->assertStringContainsString('انضم إلى نادي Maison', $this->props('/ar', self::HOME)['membership']['title']);

        app(StoreAppearanceService::class)->save([
            'membership_title' => ['ar' => 'دائرة المقرّبين', 'en' => 'The inner circle'],
            'membership_text' => ['ar' => 'دعوات خاصة لعروضنا', 'en' => ''],
        ], [], []);
        $this->assertSame('دائرة المقرّبين', $this->props('/ar', self::HOME)['membership']['title']);
        $this->assertSame('The inner circle', $this->props('/en', self::HOME)['membership']['title']);
        $this->assertSame('دعوات خاصة لعروضنا', $this->props('/en', self::HOME)['membership']['text']);

        app(StoreAppearanceService::class)->save(['membership_enabled' => false], [], []);
        $this->assertFalse(app(StoreAppearanceSettings::class)->membership_enabled);
        $this->assertNull($this->props('/ar', self::HOME)['membership']);
        $this->get($this->tenantUrl('/ar/join'))->assertNotFound();
    }

    public function test_the_members_form_has_its_own_page_while_members_are_on(): void
    {
        $this->assertNotNull($this->props('/ar/join', 'Store/Storefront/JoinMembers')['membership']);
    }

    public function test_a_visitor_joins_once_per_phone_number(): void
    {
        $this->join(['name' => 'سارة', 'phone' => '0599 123-456'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'أهلاً بك في النادي.');

        $this->join(['name' => 'سارة أحمد', 'phone' => '0599123456', 'email' => 'sara@example.test'])
            ->assertSessionHas('success', __('You are already one of our members.'));

        $member = Member::sole();
        $this->assertSame('0599123456', $member->phone);
        $this->assertSame('سارة أحمد', $member->name);
        $this->assertSame('sara@example.test', $member->email);
        $this->assertSame('ar', $member->locale);
    }

    public function test_joining_needs_a_name_and_a_real_phone_and_bots_are_not_saved(): void
    {
        $this->join(['name' => '', 'phone' => 'abc', 'email' => 'nope'])->assertSessionHasErrors(['name', 'phone', 'email']);

        $this->join(['name' => 'Bot', 'phone' => '0599000000', 'website' => 'https://spam.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'أهلاً بك في النادي.');

        $this->assertSame(0, Member::count());
    }

    public function test_joining_is_rate_limited(): void
    {
        for ($i = 0; $i < MemberService::MAX_PER_WINDOW; $i++) {
            $this->join(['name' => 'سارة', 'phone' => '059912345'.$i])->assertSessionHasNoErrors();
        }

        $this->join(['name' => 'سارة', 'phone' => '0599123459'])->assertSessionHasErrors('phone');
        $this->assertSame(MemberService::MAX_PER_WINDOW, Member::count());
    }

    public function test_staff_see_filter_and_remove_members(): void
    {
        $member = Member::factory()->create(['name' => 'Sara', 'phone' => '0599123456', 'email' => null, 'locale' => 'en']);
        Member::factory()->create(['name' => 'أحمد', 'phone' => '0599000001', 'email' => 'a@example.test', 'locale' => 'ar']);
        $this->actingAs($this->staff(['Member View List', 'Member Delete']));

        $url = $this->tenantUrl('/ar/admin/store/members');

        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Member/Index')
            ->where('table.meta.total', 2));
        $this->get($url.'?filter[search]=SARA')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)
            ->where('table.data.0.whatsapp_url', 'https://wa.me/970599123456'));
        $this->get($url.'?filter[locale]=en')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1));
        $this->get($url.'?filter[has_email]=1')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1));
        $this->get($url.'?filter[created_at_to]=2000-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));

        $this->delete($url.'/'.$member->id)->assertRedirect();
        $this->assertNull(Member::find($member->id));
    }

    public function test_members_need_the_permission(): void
    {
        $member = Member::factory()->create();
        $this->actingAs($this->staff(['Product View List']));

        $this->get($this->tenantUrl('/ar/admin/store/members'))->assertForbidden();
        $this->delete($this->tenantUrl('/ar/admin/store/members/'.$member->id))->assertForbidden();
    }

    public function test_percent_off(): void
    {
        $this->assertSame(20, (new Price(80, 100))->percentOff());
        $this->assertNull((new Price(100))->percentOff());
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function join(array $fields)
    {
        return $this->post($this->tenantUrl('/ar/join'), $fields);
    }
}
