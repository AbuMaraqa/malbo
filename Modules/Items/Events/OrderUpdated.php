<?php

namespace Modules\Items\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Items\Entities\Order;

/**
 * Staff saved an order (OrderService::update): its money, payment status or lines may have changed.
 * Dispatched inside the update's transaction, on the locked order, after OrderStatusChanged when
 * the status changed too: listeners run synchronously and their writes commit or roll back with it.
 * Never queue a listener of this event.
 */
class OrderUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly ?int $userId,
    ) {}
}
