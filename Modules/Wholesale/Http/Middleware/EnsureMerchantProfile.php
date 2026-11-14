<?php

namespace Modules\Wholesale\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * `merchant.profile`: pages a merchant keeps whatever its status (its statement of account: a
 * suspended merchant still owes what it owes). No wholesale price is ever behind it. Anyone signed
 * in without a merchant profile goes to the account's status page (JSON: 403). The merchant is
 * handed to the controller as the `merchant` request attribute.
 */
class EnsureMerchantProfile
{
    public function __construct(private readonly MerchantRepositoryInterface $merchants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $merchant = $user ? $this->merchants->findForUser((int) $user->getAuthIdentifier()) : null;

        if ($merchant === null) {
            return $request->expectsJson()
                ? response()->json(['message' => __('This page is for wholesale merchants.')], 403)
                : redirect()->route('store.wholesale.status');
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
