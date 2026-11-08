<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Tenant;
use App\Support\Locales;

/**
 * Absolute URLs on a store's host, built from the current request's scheme and port,
 * so dev links keep `:8000`.
 */
class StoreUrl
{
    public static function for(Tenant $tenant, string $path = '/'): string
    {
        // In the console Laravel builds this request from APP_URL.
        $request = app('request');
        $port = in_array($request->getPort(), [null, 80, 443], true) ? '' : ':'.$request->getPort();

        return $request->getScheme().'://'.$tenant->primaryDomain().$port.'/'.ltrim($path, '/');
    }

    /**
     * A single-use sign-in link into the store's back office as $userId.
     */
    public static function impersonation(Tenant $tenant, int|string $userId, string $locale = 'ar'): string
    {
        // The locale becomes part of the redirect path: never let it make an off-site URL.
        if (! in_array($locale, \App\Support\Locales::keys(), true)) {
            $locale = 'ar'; // the platform's default language
        }

        $token = tenancy()->impersonate($tenant, (string) $userId, "/{$locale}/admin", 'web');

        return self::for($tenant, '/impersonate/'.$token->token);
    }
}
