<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a session to the store it was started in. Every store shares the central
 * `sessions` table and the same guard session keys, so without this a session cookie
 * from store A would log in as the user with the same id in store B.
 *
 * Appended to the `web` group and placed right after StartSession in the middleware
 * priority (bootstrap/app.php), so it runs after tenancy is initialized (tenancy
 * middleware has the highest priority) and before `auth` resolves the user.
 *
 * - Store host: the first request stores `_tenant_id` (the store's id and creation time, see
 *   scopeFor(), so a new store reusing a deleted store's slug is another store). A session of another store, or an
 *   unscoped one that already holds a login (central / pre-tenancy), is invalidated and
 *   the request continues as a guest (so `auth` sends it to the login page). No 403: a
 *   stale cookie must not lock the visitor out of the store.
 * - Central host: a session carrying `_tenant_id` is invalidated the same way.
 */
class ScopeSessionToStore
{
    public const SESSION_KEY = '_tenant_id';

    /**
     * The store a session belongs to: its id and when it was created. A deleted store's slug can
     * be taken by a new store; the creation time keeps the old store's sessions out of it.
     */
    public static function scopeFor(Tenant $tenant): string
    {
        return $tenant->getTenantKey().':'.$tenant->created_at?->getTimestamp();
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $owner = $session->get(self::SESSION_KEY);

        if (! tenancy()->initialized) {
            if ($owner !== null) {
                $this->reset($request);
            }

            return $next($request);
        }

        $store = self::scopeFor(tenant());

        if ($owner === null ? $this->holdsLogin($session->all()) : $owner !== $store) {
            $this->reset($request);
        }

        $session->put(self::SESSION_KEY, $store);

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function holdsLogin(array $data): bool
    {
        return collect(array_keys($data))->contains(fn (string $key) => Str::startsWith($key, ['login_', 'password_hash_']));
    }

    /**
     * Drop the session's data (logins included) under a new id and forget any user the
     * guards already resolved.
     */
    protected function reset(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::forgetGuards();
    }
}
