<?php

namespace Modules\Items\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;

/**
 * An order moved to another status. Dispatched by OrderService inside its transaction, with the
 * order row locked: listeners run synchronously and their writes commit or roll back with it.
 * Never queue a listener of this event.
 */
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?int $userId,
    ) {}
}
