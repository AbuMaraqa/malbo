<?php

namespace Modules\Core\Services;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Modules\Core\Entities\User;

class NotificationService
{
    /**
     * The latest notifications of this user, for the bell in the header.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function latest(User $user, int $limit = 10): Collection
    {
        return $user->notifications()->latest()->take($limit)->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? null,
                'message' => $notification->data['message'] ?? '',
                'url' => $this->safeUrl($notification->data['url'] ?? null),
                'time' => $notification->created_at?->diffForHumans(),
                'read' => $notification->read_at !== null,
            ])
            ->toBase();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Only a notification of this user: anything else is a 404.
     */
    public function markAsRead(User $user, string $id): void
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($id)->firstOrFail();

        $notification->markAsRead();
    }

    /**
     * The link goes in an href, so only web and same-site links pass.
     */
    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || $url === '#') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
