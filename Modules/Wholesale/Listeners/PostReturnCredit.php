<?php

namespace Modules\Wholesale\Listeners;

use Modules\Items\Events\ReturnRequestCompleted;
use Modules\Wholesale\Services\MerchantLedger;

/**
 * A completed return on a B2B order credits its merchant the refund. Runs inside
 * ReturnRequestService's transaction (never queued).
 */
class PostReturnCredit
{
    public function __construct(private readonly MerchantLedger $ledger) {}

    public function handle(ReturnRequestCompleted $event): void
    {
        $this->ledger->postReturnCredit($event->request, $event->order, $event->refund, $event->userId);
    }
}
