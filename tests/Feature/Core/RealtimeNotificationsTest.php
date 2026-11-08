<?php

namespace Tests\Feature\Core;

use App\Support\Realtime;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Symfony\Component\Finder\Finder;
use Tests\DatabaseTestCase;

class RealtimeNotificationsTest extends DatabaseTestCase
{
    private const REVERB = [
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'public-key',
        'broadcasting.connections.reverb.secret' => 'secret',
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => 'ws.example.test',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ];

    public function test_it_is_off_and_silent_with_log_or_null(): void
    {
        Event::fake([BroadcastNotificationCreated::class]);

        foreach (['log', 'null'] as $connection) {
            config(['broadcasting.default' => $connection, 'broadcasting.connections.reverb.key' => 'public-key']);

            $user = $this->staff();
            $this->assertFalse(Realtime::enabled());
            $this->assertNull(Realtime::client($user));

            $user->notify(new GeneralNotification('Title', 'Message'));
            $this->assertSame(1, $user->unreadNotifications()->count(), 'The database notification is still saved.');

            $this->actingAs($user)->get($this->siteUrl('/ar/admin'))->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('realtime', null)->where('unreadNotifications', 1));
        }

        Event::assertNotDispatched(BroadcastNotificationCreated::class);
    }

    public function test_a_store_without_firebase_credentials_still_saves_the_notification(): void
    {
        config(['firebase.projects.app.credentials' => null]);

        $user = $this->staff();
        $user->deviceTokens()->create(['token' => 'device-token', 'device_name' => 'phone']);

        $user->notify(new GeneralNotification('Title', 'Message'));

        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_it_is_off_without_a_key(): void
    {
        config([...self::REVERB, 'broadcasting.connections.reverb.key' => null]);

        $this->assertFalse(Realtime::enabled());
    }

    public function test_when_on_a_new_notification_is_broadcast_to_the_users_channel(): void
    {
        config(self::REVERB);
        Event::fake([BroadcastNotificationCreated::class]);

        $user = $this->staff();
        $user->notify(new GeneralNotification('Title', 'Message'));

        $this->assertSame(1, $user->unreadNotifications()->count());

        Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($user) {
            $this->assertSame('private-users.'.$user->id, (string) $event->broadcastOn()[0]);
            $this->assertSame('BroadcastNotificationCreated', $event->broadcastAs());

            return true;
        });
    }

    public function test_when_on_the_page_gets_public_values_only(): void
    {
        config(self::REVERB);
        $user = $this->staff();

        $response = $this->actingAs($user)->get($this->siteUrl('/ar/admin'))->assertOk();

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('realtime.key', 'public-key')
            ->where('realtime.host', 'ws.example.test')
            ->where('realtime.port', 8080)
            ->where('realtime.tls', false)
            ->where('realtime.channel', 'users.'.$user->id)
            ->missing('realtime.secret'));
    }

    public function test_the_storefront_never_gets_the_client(): void
    {
        config(self::REVERB);

        $this->get($this->siteUrl('/ar'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->missing('realtime'));
    }

    public function test_only_the_user_may_listen_to_their_channel(): void
    {
        $callback = Broadcast::getChannels()['users.{id}'] ?? null;
        $this->assertNotNull($callback, 'The channel is registered.');

        $user = $this->staff();

        $this->assertTrue($callback($user, (string) $user->id));
        $this->assertFalse($callback($user, (string) ($user->id + 1)), 'Another user.');
    }

    public function test_the_auth_endpoint_signs_only_the_users_own_channel(): void
    {
        config(self::REVERB);

        // The channels register on the driver chosen at boot: repeat them on the Reverb one.
        $manager = $this->app->make(BroadcastFactory::class);
        $this->assertInstanceOf(BroadcastManager::class, $manager);
        $manager->purge('reverb');
        require base_path('routes/channels.php');

        $user = $this->staff();

        $this->actingAs($user, 'web')->postJson($this->siteUrl('/broadcasting/auth'), [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-users.'.$user->id,
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->actingAs($user, 'web')->postJson($this->siteUrl('/broadcasting/auth'), [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-users.'.($user->id + 1),
        ])->assertForbidden();

        $this->actingAs($user, 'web')->postJson($this->siteUrl('/broadcasting/auth'), [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])->assertForbidden();
    }

    public function test_the_client_is_only_ever_imported_dynamically(): void
    {
        $static = [];

        foreach (Finder::create()->files()->name(['*.vue', '*.ts'])->in([resource_path('js'), ...glob(base_path('Modules/*/resources/js'))]) as $file) {
            if (preg_match("/^\s*import\s[^(]*from\s+'(laravel-echo|pusher-js)'/m", $file->getContents())) {
                $static[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $static);
    }

    private function staff(): User
    {
        return User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::lower(Str::random(12)).'@realtime.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->assignRole('Super Admin');
    }
}
