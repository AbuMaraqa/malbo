<?php

namespace Modules\Store\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Modules\Store\Entities\MarketingOptOut;
use Modules\Store\Repositories\Contracts\MarketingOptOutRepositoryInterface;
use Modules\Store\Support\Phone;

/**
 * "No more marketing messages": the signed link at the end of every cart reminder and stock alert,
 * and the list it writes to. The link carries the contact encrypted (it never shows in a URL or a
 * log in clear) and signed (it cannot be pointed at someone else).
 */
class OptOutService
{
    public function __construct(private readonly MarketingOptOutRepositoryInterface $optOuts) {}

    /**
     * Whether this email or phone (as typed) asked for no more messages.
     */
    public function optedOut(?string $email, ?string $phone): bool
    {
        return $this->optOuts->optedOut(self::email($email), self::phone($phone));
    }

    public function optOut(string $channel, string $value): void
    {
        $value = $channel === MarketingOptOut::EMAIL ? self::email($value) : self::phone($value);

        if ($value !== null) {
            $this->optOuts->query()->insertOrIgnore([
                'channel' => $channel,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * The page that opts this contact out (signed, never expires), as an absolute address.
     */
    public function url(string $channel, string $value, string $locale): string
    {
        // Signed as a path (the route checks `signed:relative`): it holds whatever host or proxy the
        // site is reached through; the mail gets it on the site's address.
        return url(URL::signedRoute('store.unsubscribe', [
            'locale' => $locale,
            'c' => Crypt::encryptString(json_encode([$channel, $value])),
        ], absolute: false));
    }

    /**
     * The contact a link carries, or null when it is not one of ours.
     *
     * @return array{channel: string, value: string}|null
     */
    public function decode(mixed $token): ?array
    {
        try {
            $data = is_string($token) ? json_decode(Crypt::decryptString($token), true) : null;
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || count($data) !== 2 || ! in_array($data[0], [MarketingOptOut::EMAIL, MarketingOptOut::PHONE], true) || ! is_string($data[1])) {
            return null;
        }

        return ['channel' => $data[0], 'value' => $data[1]];
    }

    /**
     * The contact half hidden, for the page: b***@example.com, 059****456.
     */
    public static function mask(string $channel, string $value): string
    {
        if ($channel === MarketingOptOut::EMAIL) {
            [$name, $domain] = array_pad(explode('@', $value, 2), 2, '');

            return mb_substr($name, 0, 1).'***@'.$domain;
        }

        $digits = Phone::canonical($value);

        return substr($digits, 0, 3).str_repeat('*', max(strlen($digits) - 6, 1)).substr($digits, -3);
    }

    public static function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    public static function phone(?string $phone): ?string
    {
        $digits = Phone::normalize($phone);

        return $digits === '' ? null : $digits;
    }
}
