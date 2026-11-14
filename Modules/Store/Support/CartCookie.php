<?php

namespace Modules\Store\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The cookie that holds a buyer's cart token: long-lived, http-only and encrypted like every
 * cookie of the site.
 */
final class CartCookie
{
    public const NAME = 'store_cart';

    /** Sixty days, in minutes. */
    private const MINUTES = 86400;

    public static function token(Request $request): ?string
    {
        $token = $request->cookie(self::NAME);

        return is_string($token) && preg_match('/^[A-Za-z0-9]{40,64}$/', $token) ? $token : null;
    }

    public static function queue(string $token): void
    {
        Cookie::queue(Cookie::make(self::NAME, $token, self::MINUTES, httpOnly: true, sameSite: 'lax'));
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::NAME));
    }
}
