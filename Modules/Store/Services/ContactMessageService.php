<?php

namespace Modules\Store\Services;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Store\Entities\ContactMessage;
use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;

/**
 * Messages from the storefront's contact page and the app: saved as sent (trimmed), read and
 * cleared by the staff.
 */
class ContactMessageService
{
    private const FIELDS = ['name', 'phone', 'email', 'subject', 'message'];

    public const TOPICS = ['product', 'order', 'delivery', 'returns', 'other'];

    /** Messages one visitor may send per window, from the storefront and the app together. */
    public const MAX_PER_WINDOW = 3;

    public const WINDOW_SECONDS = 600;

    public function __construct(private ContactMessageRepositoryInterface $contactMessages) {}

    /**
     * @return array<string, string> key => label in the current locale
     */
    public static function topics(): array
    {
        return [
            'product' => __('A question about a product'),
            'order' => __('An order'),
            'delivery' => __('Delivery'),
            'returns' => __('Exchange or return'),
            'other' => __('Something else'),
        ];
    }

    /**
     * Counts one message from $ip; false once it sent MAX_PER_WINDOW in the window.
     */
    public function allowFrom(?string $ip): bool
    {
        $key = 'store-contact:'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_WINDOW)) {
            return false;
        }
        RateLimiter::hit($key, self::WINDOW_SECONDS);

        return true;
    }

    /**
     * @param  array<string, mixed>  $fields  name, phone, email, subject, message (validated by the page)
     */
    public function receive(array $fields, ?string $ip = null): ContactMessage
    {
        $attributes = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            $attributes[$field] = $value === '' ? null : $value;
        }

        /** @var ContactMessage */
        return $this->contactMessages->create([
            ...$attributes,
            'locale' => app()->getLocale(),
            'ip' => $ip,
        ]);
    }

    public function markRead(ContactMessage $message): ContactMessage
    {
        return $message->isRead() ? $message : $this->contactMessages->update($message, ['read_at' => now()]);
    }

    public function markUnread(ContactMessage $message): ContactMessage
    {
        return $this->contactMessages->update($message, ['read_at' => null]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function markManyRead(array $ids): int
    {
        return $this->contactMessages->setRead($ids, true);
    }

    public function delete(ContactMessage $message): void
    {
        $this->contactMessages->delete($message);
    }
}
