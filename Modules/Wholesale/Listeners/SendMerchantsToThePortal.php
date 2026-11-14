<?php

namespace Modules\Wholesale\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;

/**
 * A merchant signing in on the storefront's login lands in the portal: its catalog once approved,
 * its status page before that. Only where nothing else was asked for (a page that sent them to sign
 * in still wins), and only on the storefront's login, so Store needs to know nothing of it.
 */
class SendMerchantsToThePortal
{
    public function __construct(private readonly MerchantRepositoryInterface $merchants) {}

    public function handle(Login $event): void
    {
        $request = request();

        if (! $request->routeIs('store.account.login.store') || ! $request->hasSession() || $request->session()->has('url.intended')) {
            return;
        }

        $merchant = $this->merchants->findForUser((int) $event->user->getAuthIdentifier());

        if ($merchant === null) {
            return;
        }

        $request->session()->put('url.intended', $merchant->isApproved() ? route('store.wholesale.catalog') : route('store.wholesale.status'));
    }
}
