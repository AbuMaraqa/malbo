<?php

namespace Modules\Wholesale\Listeners;

use Modules\Items\Enums\OrderChannel;
use Modules\Items\Events\OrderUpdated;
use Modules\Wholesale\Services\MerchantLedger;

/**
 * Staff saved a B2B order: an invoiced total that changed is posted as a difference, and cash on
 * delivery follows the payment status. Runs inside OrderService's transaction (never queued).
 */
class SyncB2BOrderEntries
{
    public function __construct(private readonly MerchantLedger $ledger) {}

    public function handle(OrderUpdated $event): void
    {
        if ($event->order->channel !== OrderChannel::B2B) {
            return;
        }

        $this->ledger->syncOrderTotal($event->order, $event->userId);
        $this->ledger->syncCashPayment($event->order, $event->userId);
    }
}
