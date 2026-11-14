<?php

namespace Modules\Items\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\ReturnRequest;

/**
 * A return or exchange request was completed: its goods are back in stock and $refund (0 for an
 * exchange) is what the buyer is owed. Dispatched by ReturnRequestService inside its transaction,
 * with the request and the order locked, before the order may move to RETURNED or EXCHANGED:
 * listeners run synchronously and their writes commit or roll back with it. Never queue a listener.
 */
class ReturnRequestCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly ReturnRequest $request,
        public readonly Order $order,
        public readonly float $refund,
        public readonly ?int $userId,
    ) {}
}
