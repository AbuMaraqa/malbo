<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Enums\UserRole;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store admin is for staff only. Staff = a user with at least one role other than
 * Customer, or with any direct permission. Every other signed-in user (Customer-only,
 * or no role and no permission) is sent to the storefront. Guests pass: `auth` handles them.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $this->isStaff($user)) {
            return redirect()->route('home', ['locale' => app()->getLocale()]);
        }

        return $next($request);
    }

    protected function isStaff(object $user): bool
    {
        if (! method_exists($user, 'getRoleNames')) {
            return false;
        }

        return $user->getRoleNames()->contains(fn (string $role) => $role !== UserRole::CUSTOMER->value)
            || $user->getDirectPermissions()->isNotEmpty();
    }
}
