<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A store that is not active (e.g. suspended) answers every request with 503: the
 * "store unavailable" page, or a JSON error for API requests. Central requests pass.
 */
class EnsureStoreIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = tenant();

        if (! $store instanceof Tenant || $store->isActive()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => false,
                'message' => 'Store unavailable.',
                'data'    => null,
            ], 503, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->view('store.unavailable', ['store' => $store], 503);
    }
}
