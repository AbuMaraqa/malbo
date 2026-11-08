<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * For routes served on every host (`/`, `/{locale}`, Livewire endpoints): on a central
 * (platform) host the request stays central; on any other host the store is identified by
 * its domain (unknown host -> 404) and must be active (EnsureStoreIsActive).
 */
class InitializeTenancyIfTenantHost
{
    public function __construct(
        protected InitializeTenancyByDomain $initializeTenancy,
        protected EnsureStoreIsActive $ensureStoreIsActive,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            return $next($request);
        }

        return $this->initializeTenancy->handle(
            $request,
            fn (Request $request) => $this->ensureStoreIsActive->handle($request, $next)
        );
    }
}
