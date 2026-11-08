<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Store\Entities\ContactMessage;
use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;
use Modules\Store\Services\ContactMessageService;

/**
 * The storefront contact form and the admin inbox it fills.
 */
class ContactFormTest extends StorefrontTestCase
{
    private const INBOX = '/ar/admin/store/messages';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('store-contact:testing:127.0.0.1');
    }

    public function test_contact_page_has_the_topics_and_the_arabic_texts(): void
    {
        $props = $this->props('/ar/contact', 'Store/Storefront/Contact');

        $this->assertSame('التوصيل', collect($props['topics'])->firstWhere('value', 'delivery')['label']);
        $this->assertSame('أرسل لنا رسالة', $props['translations']['Send us a message']);
    }

    public function test_a_buyer_sends_a_message(): void
    {
        $this->send(['name' => '  سارة  ', 'phone' => '0599 123 456', 'topic' => 'delivery', 'message' => 'متى يصل طلبي إلى نابلس؟'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'شكراً لك، وصلتنا رسالتك.');

        $message = ContactMessage::sole();
        $this->assertSame('سارة', $message->name);
        $this->assertSame('0599 123 456', $message->phone);
        $this->assertNull($message->email);
        $this->assertSame('التوصيل', $message->subject);
        $this->assertSame('ar', $message->locale);
        $this->assertFalse($message->isRead());
    }

    public function test_a_phone_or_an_email_is_needed_and_fields_are_checked(): void
    {
        $this->send(['name' => '', 'message' => 'قصير'])
            ->assertSessionHasErrors(['name', 'phone' => 'اترك رقم هاتف أو بريداً إلكترونياً لنتمكن من الرد عليك.', 'email', 'message']);

        $this->send(['name' => 'سارة', 'email' => 'not-an-email', 'topic' => 'hack', 'message' => 'رسالة طويلة بما يكفي'])
            ->assertSessionHasErrors(['email', 'topic']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_bot_filling_the_hidden_field_is_thanked_but_not_saved(): void
    {
        $this->send(['name' => 'Bot', 'email' => 'bot@spam.test', 'message' => 'Buy cheap followers now', 'website' => 'https://spam.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_visitor_can_send_only_a_few_messages_in_a_row(): void
    {
        for ($i = 0; $i < ContactMessageService::MAX_PER_WINDOW; $i++) {
            $this->send(['name' => 'سارة', 'phone' => '0599123456', 'message' => 'رسالة رقم '.$i.' للتجربة'])->assertSessionHasNoErrors();
        }

        $this->send(['name' => 'سارة', 'phone' => '0599123456', 'message' => 'رسالة زائدة للتجربة'])
            ->assertSessionHasErrors('message')
            ->assertSessionMissing('success');

        $this->assertSame(ContactMessageService::MAX_PER_WINDOW, ContactMessage::count());
    }

    public function test_staff_read_and_clear_messages_in_the_inbox(): void
    {
        $new = ContactMessage::factory()->create(['name' => 'سارة', 'message' => 'سؤال عن المقاسات']);
        $old = ContactMessage::factory()->read()->create(['name' => 'أحمد']);
        $this->actingAs($this->inboxStaff());

        $this->assertSame(1, app(ContactMessageRepositoryInterface::class)->unreadCount());

        $this->get($this->tenantUrl(self::INBOX))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/ContactMessage/Index')
                ->where('table.meta.total', 2)
                ->where('table.data.0.message', fn ($message) => in_array($message, ['سؤال عن المقاسات', $old->message], true)));

        $this->post($this->tenantUrl(self::INBOX."/{$new->id}/open"))->assertRedirect();
        $this->assertTrue($new->fresh()->isRead());

        $this->post($this->tenantUrl(self::INBOX."/{$new->id}/unread"))->assertRedirect();
        $this->assertFalse($new->fresh()->isRead());

        $this->delete($this->tenantUrl(self::INBOX."/{$old->id}"))->assertRedirect();
        $this->assertNull(ContactMessage::find($old->id));
    }

    public function test_inbox_filters_and_search(): void
    {
        ContactMessage::factory()->create(['name' => 'Sara Nasser', 'email' => null, 'locale' => 'en', 'subject' => 'Delivery']);
        ContactMessage::factory()->read()->create(['name' => 'أحمد', 'phone' => null]);
        $this->actingAs($this->inboxStaff());

        $total = fn (string $query, int $expected) => $this->get($this->tenantUrl(self::INBOX.'?'.$query))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', $expected));

        $total('filter[read]=0', 1);
        $total('filter[read]=1', 1);
        $total('filter[search]=sara', 1); // another letter case
        $total('filter[subject]=delivery', 1);
        $total('filter[locale]=en', 1);
        $total('filter[has_phone]=0', 1);
        $total('filter[has_email]=0', 1);
        $total('filter[created_at_from]=not-a-date', 2);
        $total('sort=name', 2);
    }

    public function test_mark_many_read(): void
    {
        $messages = ContactMessage::factory()->count(3)->create();
        $this->actingAs($this->inboxStaff());

        $this->post($this->tenantUrl(self::INBOX.'/read'), ['ids' => $messages->take(2)->modelKeys()])->assertRedirect();

        $this->assertSame(1, app(ContactMessageRepositoryInterface::class)->unreadCount());
    }

    public function test_the_inbox_needs_the_permission(): void
    {
        $this->get($this->tenantUrl(self::INBOX))->assertRedirect();

        $message = ContactMessage::factory()->create();
        $this->actingAs($this->staff(['Product View List']));

        $this->get($this->tenantUrl(self::INBOX))->assertForbidden();
        $this->post($this->tenantUrl(self::INBOX."/{$message->id}/open"))->assertForbidden();
        $this->post($this->tenantUrl(self::INBOX."/{$message->id}/unread"))->assertForbidden();
        $this->post($this->tenantUrl(self::INBOX.'/read'), ['ids' => [$message->id]])->assertForbidden();
        $this->delete($this->tenantUrl(self::INBOX."/{$message->id}"))->assertForbidden();
    }

    public function test_a_bad_or_unknown_id_touches_nothing(): void
    {
        $this->actingAs($this->inboxStaff());

        $this->post($this->tenantUrl(self::INBOX.'/abc/open'))->assertStatus(405); // no such route for POST
        $this->post($this->tenantUrl(self::INBOX.'/999999/open'))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function send(array $fields)
    {
        return $this->post($this->tenantUrl('/ar/contact'), $fields);
    }

    private function inboxStaff(): User
    {
        return $this->staff(['Contact Message View List', 'Contact Message View', 'Contact Message Update', 'Contact Message Delete']);
    }
}
