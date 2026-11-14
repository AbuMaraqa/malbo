<?php

namespace Modules\Items\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Items that could not be ordered can be ordered again (InventoryService::orderable went from false
 * to true): stock arrived, a return or a cancellation gave some back, or a correction. Dispatched
 * by InventoryService once the change is committed, never for a change that rolled back.
 */
class ProductsBecameOrderable
{
    use Dispatchable;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public readonly array $productIds) {}
}
