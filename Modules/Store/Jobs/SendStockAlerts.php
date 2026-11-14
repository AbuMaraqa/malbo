<?php

namespace Modules\Store\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Store\Services\StockAlertService;

/**
 * Back-in-stock alerts of items that can be ordered again (ProductsBecameOrderable), on the database
 * queue: the request that received the stock never waits for them. StockAlertService claims each
 * alert before it sends it, so a retried job never writes to anyone twice. The scheduler drains the
 * queue every minute (routes/console.php): no worker has to be kept running.
 */
class SendStockAlerts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public readonly array $productIds) {}

    public function handle(StockAlertService $alerts): void
    {
        $alerts->notifyAvailable($this->productIds);
    }
}
