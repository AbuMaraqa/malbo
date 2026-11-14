<?php

namespace Modules\Items\Contracts;

use Illuminate\Container\Attributes\Bind;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Services\RefuseOnAccount;

/**
 * Whether staff may put an order on account (PaymentMethod::ON_ACCOUNT): never by default
 * (RefuseOnAccount); the wholesale module binds its own, for its merchants' credit. Items knows
 * nothing of who does. Called by OrderService inside the order's transaction, before it is saved.
 */
#[Bind(RefuseOnAccount::class)]
interface OnAccountPolicy
{
    /**
     * @param  Order|null  $order  the order being edited (its saved fields); null for a new order made in the admin
     * @param  float  $increase  what the order's total grows by with this edit (0 or more); a new order's whole total
     *
     * @throws ValidationException on `payment_method`
     */
    public function assertAllowed(?Order $order, float $increase): void;
}
