<?php

namespace Modules\Wholesale\Services;

use Illuminate\Validation\ValidationException;
use Modules\Items\Contracts\OnAccountPolicy;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;

/**
 * Staff may put an order on account only when it is a merchant's wholesale order and the merchant's
 * credit has room for what the edit adds (MerchantCredit, on the locked merchant row: the order is
 * already counted among the merchant's open orders or in its balance).
 */
class WholesaleOnAccountPolicy implements OnAccountPolicy
{
    public function __construct(
        private readonly MerchantRepositoryInterface $merchants,
        private readonly MerchantCredit $credit,
    ) {}

    public function assertAllowed(?Order $order, float $increase): void
    {
        $merchant = $order !== null && $order->channel === OrderChannel::B2B && $order->user_id !== null
            ? $this->merchants->findForUser((int) $order->user_id)
            : null;

        if ($merchant === null) {
            throw ValidationException::withMessages(['payment_method' => __('Only a wholesale order of a merchant with credit can be on account.')]);
        }

        try {
            $this->credit->assertRoom($merchant, $increase);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['payment_method' => collect($e->errors())->flatten()->first()]);
        }
    }
}
