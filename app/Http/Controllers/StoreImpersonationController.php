<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopeSessionToStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

/**
 * Signs a user into this store with a token issued by the platform (StoreUrl::impersonation).
 * Tokens are single use, expire after 60 seconds and only work on the store they were issued for.
 *
 * Not stancl's UserImpersonation::makeResponse(): it reads the token and deletes it only after
 * signing in, so two requests with the same link could both sign in, and its expiry check
 * accepts tokens dated in the future.
 */
class StoreImpersonationController extends Controller
{
    public const TTL_SECONDS = 60;

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $record = ImpersonationToken::find($token) ?? abort(404);

        abort_unless((string) $record->tenant_id === (string) tenant()->getTenantKey(), 403);

        if ($record->created_at->isFuture() || $record->created_at->copy()->addSeconds(self::TTL_SECONDS)->isPast()) {
            $record->delete();
            abort(403);
        }

        // Claim the token: only the request whose delete removes the row may sign in.
        $claimed = ImpersonationToken::whereKey($record->token)
            ->where('tenant_id', $record->tenant_id)
            ->delete() === 1;
        abort_unless($claimed, 404);

        $guard = Auth::guard($record->auth_guard ?: 'web');
        $user = $guard->getProvider()->retrieveById($record->user_id) ?? abort(404);

        // Start from an empty session (no earlier logins or carts), still bound to this store.
        $request->session()->invalidate();
        $request->session()->put(ScopeSessionToStore::SESSION_KEY, ScopeSessionToStore::scopeFor(tenant()));

        $guard->login($user); // regenerates the session id

        return redirect(str_starts_with($record->redirect_url, '/') && ! str_starts_with($record->redirect_url, '//')
            ? $record->redirect_url
            : '/');
    }
}
