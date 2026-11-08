<?php

namespace Modules\Store\Services;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Store\Entities\Member;
use Modules\Store\Repositories\Contracts\MemberRepositoryInterface;

/**
 * The store's members list: a visitor joins once per phone number; joining again updates the
 * name and email.
 */
class MemberService
{
    /** Sign-ups one visitor may send per window, from the storefront and the app together. */
    public const MAX_PER_WINDOW = 3;

    public const WINDOW_SECONDS = 600;

    public function __construct(private MemberRepositoryInterface $members) {}

    /**
     * Counts one sign-up from $ip; false once it sent MAX_PER_WINDOW in the window.
     */
    public function allowFrom(?string $ip): bool
    {
        $key = 'store-members:'.tenant('id').':'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_WINDOW)) {
            return false;
        }
        RateLimiter::hit($key, self::WINDOW_SECONDS);

        return true;
    }

    /**
     * @return array{member: Member, new: bool}
     */
    public function join(string $name, string $phone, ?string $email = null, ?string $ip = null): array
    {
        $phone = self::digits($phone);
        $attributes = [
            'name' => trim($name),
            'email' => filled($email) ? trim((string) $email) : null,
        ];

        if ($member = $this->members->findByPhone($phone)) {
            /** @var Member $member */
            $member = $this->members->update($member, array_filter($attributes));

            return ['member' => $member, 'new' => false];
        }

        /** @var Member $member */
        $member = $this->members->create([...$attributes, 'phone' => $phone, 'locale' => app()->getLocale(), 'ip' => $ip]);

        return ['member' => $member, 'new' => true];
    }

    public function delete(Member $member): void
    {
        $this->members->delete($member);
    }

    public static function digits(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }
}
