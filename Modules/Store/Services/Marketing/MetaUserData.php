<?php

namespace Modules\Store\Services\Marketing;

use Illuminate\Http\Request;
use Modules\Store\Support\Phone;

/**
 * The `user_data` of a Conversions API event, as Meta asks for it: email and phone normalised then
 * SHA-256 hashed (never sent in clear), the account id hashed as `external_id`, the visitor's IP and
 * user agent, and the `_fbp` / `_fbc` cookies the Pixel set (or `fbc` built from a `fbclid` link).
 */
final class MetaUserData
{
    /** National numbers on Palestinian networks (Jawwal 059, Ooredoo 056); the rest are Israeli. */
    private const PALESTINIAN_PREFIXES = ['59', '56'];

    /**
     * @return array<string, mixed>
     */
    public static function for(Request $request, ?string $email = null, ?string $phone = null, ?int $userId = null): array
    {
        $fbclid = $request->query('fbclid');

        return array_filter([
            'em' => ($hash = self::email($email)) ? [$hash] : null,
            'ph' => ($hash = self::phone($phone)) ? [$hash] : null,
            'external_id' => $userId ? [hash('sha256', (string) $userId)] : null,
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbp' => self::cookie($request, '_fbp'),
            'fbc' => self::cookie($request, '_fbc')
                ?? (is_string($fbclid) && preg_match('/^[\w\-]{1,500}$/', $fbclid) ? 'fb.1.'.((int) floor(microtime(true) * 1000)).'.'.$fbclid : null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Trimmed and lowercased, then hashed; null when nothing was typed.
     */
    public static function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : hash('sha256', $email);
    }

    /**
     * Digits with the country code, no `+` and no leading zero, then hashed. A number typed with
     * 970 or 972 keeps it; a national one gets 970 on Palestinian networks, else 972.
     */
    public static function phone(?string $phone): ?string
    {
        $national = Phone::normalize($phone);

        if ($national === '') {
            return null;
        }

        $typed = (string) preg_replace('/\D+/', '', (string) $phone);
        $typed = str_starts_with($typed, '00') ? substr($typed, 2) : $typed;

        $code = match (true) {
            strlen($typed) > 10 && in_array(substr($typed, 0, 3), ['970', '972'], true) => substr($typed, 0, 3),
            in_array(substr($national, 0, 2), self::PALESTINIAN_PREFIXES, true) => '970',
            default => '972',
        };

        return hash('sha256', $code.$national);
    }

    private static function cookie(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);

        return is_string($value) && preg_match('/^fb\.\d\.\d+\.[\w\-.]{1,500}$/', $value) ? $value : null;
    }
}
