<?php

namespace Modules\Wholesale\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * `merchant.approved`: the portal's wholesale prices are for approved merchants only. Read from the
 * database on every request, so a merchant suspended a minute ago sees nothing now. Anyone else who
 * is signed in (a pending, rejected or suspended merchant, a buyer, staff) is sent to the account's
 * status page; a JSON request gets a 403. Guests never get here: `auth` sends them to sign in.
 * The approved merchant is handed to the controller as the `merchant` request attribute.
 */
class EnsureApprovedMerchant
{
    public function __construct(private readonly MerchantRepositoryInterface $merchants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $merchant = $user ? $this->merchants->findForUser((int) $user->getAuthIdentifier()) : null;

        if ($merchant === null || ! $merchant->isApproved()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Wholesale prices are for approved merchants only.')], 403);
            }

            return $user
                ? redirect()->route('store.wholesale.status')
                : redirect()->guest(route('store.account.login'));
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
