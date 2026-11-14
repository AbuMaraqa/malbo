<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Support\PortalNav;

/**
 * The signed-in account's wholesale status: where its application stands, never a price. Anyone the
 * portal turns away lands here and is told why.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly MerchantRepositoryInterface $merchants,
        private readonly StorefrontLayout $layout,
    ) {}

    public function status(Request $request): Response
    {
        $merchant = $this->merchants->findForUser((int) $request->user()->id);
        $merchant?->loadMissing('statusChanges');

        $status = $merchant?->status;
        $reason = $merchant && in_array($status, [MerchantStatus::REJECTED, MerchantStatus::SUSPENDED], true)
            ? $merchant->statusChanges->first(fn ($change) => $change->to_status === $status)?->note
            : null;

        return Inertia::render('Wholesale/Portal/Status', [
            ...$this->layout->props(['title' => __('Wholesale account'), 'noindex' => true]),
            'merchant' => $merchant ? [
                'shop_name' => $merchant->shop_name,
                'status' => $status->value,
                'status_label' => $status->getLabel(),
                'reason' => $reason,
                'applied_at' => $merchant->created_at?->format('Y-m-d'),
            ] : null,
            'nav' => $merchant?->isApproved() ? PortalNav::links() : null,
            'applyUrl' => route('store.wholesale.apply'),
        ]);
    }
}
