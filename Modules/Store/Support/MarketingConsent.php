<?php

namespace Modules\Store\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * A visitor's answer to the marketing cookies banner: `granted` or `denied`, kept a year in an
 * encrypted, http-only cookie that only `POST /{locale}/consent` sets. No cookie means the visitor
 * has not answered, which counts as declined: no Pixel, no server events.
 */
final class MarketingConsent
{
    public const COOKIE = 'store_consent';

    public const GRANTED = 'granted';

    public const DENIED = 'denied';

    /** A year, in minutes. */
    private const MINUTES = 525600;

    public static function choice(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return in_array($value, [self::GRANTED, self::DENIED], true) ? $value : null;
    }

    public static function granted(Request $request): bool
    {
        return self::choice($request) === self::GRANTED;
    }

    public static function queue(string $choice): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, $choice, self::MINUTES, httpOnly: true, sameSite: 'lax'));
    }
}
