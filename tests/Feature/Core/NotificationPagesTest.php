<?php

namespace Tests\Feature\Core;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Tests\TenantTestCase;

class NotificationPagesTest extends TenantTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->user = User::forceCreate(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password'])->assignRole('User');
    }

    public function test_the_list_shows_my_latest_notifications_only(): void
    {
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']);

        $this->notification($this->user, ['title' => 'Mine', 'message' => 'Hello', 'url' => '/ar/admin/users']);
        $this->notification($other, ['title' => 'Theirs', 'message' => 'Secret']);

        $this->actingAs($this->user)
            ->getJson($this->tenantUrl('/ar/admin/notifications'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine')
            ->assertJsonPath('data.0.url', '/ar/admin/users')
            ->assertJsonPath('data.0.read', false);
    }

    public function test_an_unsafe_link_is_dropped(): void
    {
        foreach (['javascript:alert(1)', '//evil.example', '#'] as $url) {
            $this->notification($this->user, ['message' => 'x', 'url' => $url]);
        }

        $this->actingAs($this->user)
            ->getJson($this->tenantUrl('/ar/admin/notifications'))
            ->assertJsonPath('data.0.url', null)
            ->assertJsonPath('data.1.url', null)
            ->assertJsonPath('data.2.url', null);
    }

    public function test_read_all_clears_my_unread_count_and_not_anyone_elses(): void
    {
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']);

        $this->notification($this->user, ['message' => 'a']);
        $this->notification($this->user, ['message' => 'b']);
        $this->notification($other, ['message' => 'c']);

        $this->actingAs($this->user)
            ->post($this->tenantUrl('/ar/admin/notifications/read'))
            ->assertRedirect();

        $this->assertSame(0, $this->user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());

        $this->actingAs($this->user)
            ->get($this->tenantUrl('/ar/admin/profile'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 0));
    }

    public function test_one_notification_can_be_marked_read_but_only_my_own(): void
    {
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']);

        $mine = $this->notification($this->user, ['message' => 'a']);
        $theirs = $this->notification($other, ['message' => 'b']);

        $this->actingAs($this->user)
            ->post($this->tenantUrl('/ar/admin/notifications/'.$mine->id.'/read'))
            ->assertRedirect();

        $this->assertNotNull($mine->fresh()->read_at);

        $this->actingAs($this->user)
            ->post($this->tenantUrl('/ar/admin/notifications/'.$theirs->id.'/read'))
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_guests_cannot_read_notifications(): void
    {
        $this->getJson($this->tenantUrl('/ar/admin/notifications'))->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notification(User $user, array $data): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Notification',
            'data' => $data,
        ]);
    }
}
