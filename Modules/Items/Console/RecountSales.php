<?php

namespace Modules\Items\Console;

use Illuminate\Console\Command;
use Modules\Items\Services\SalesCounter;

/**
 * Rebuilds every product's `sales_count` from the orders (SalesCounter::recount): after an import,
 * or to repair a count. Safe to run again.
 */
class RecountSales extends Command
{
    protected $signature = 'items:recount-sales';

    protected $description = 'Rebuild the products\' sales counts (best selling) from the orders';

    public function handle(SalesCounter $sales): int
    {
        $changed = $sales->recount();

        $this->info("Sales counts rebuilt: {$changed} product(s) changed.");

        return self::SUCCESS;
    }
}
