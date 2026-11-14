<?php

namespace Modules\Wholesale\Listeners;

use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Events\OrderStatusChanged;
use Modules\Wholesale\Services\MerchantLedger;

/**
 * A B2B order delivered is invoiced to its merchant (and its cash recorded when already marked
 * paid); one returned after delivery is credited what its goods have left. Runs inside OrderService's transaction (never queued).
 */
class PostB2BOrderEntries
{
    public function __construct(private readonly MerchantLedger $ledger) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->order->channel !== OrderChannel::B2B) {
            return;
        }

        match ($event->to) {
            OrderStatus::DELIVERED => $this->delivered($event),
            OrderStatus::RETURNED => $this->ledger->postReturnedOrderCredit($event->order, $event->userId),
            default => null,
        };
    }

    private function delivered(OrderStatusChanged $event): void
    {
        $this->ledger->postInvoice($event->order, $event->userId);
        // Cash on delivery marked paid before it was delivered.
        $this->ledger->syncCashPayment($event->order, $event->userId);
    }
}
