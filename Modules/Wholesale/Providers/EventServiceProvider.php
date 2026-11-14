<?php

namespace Modules\Wholesale\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Items\Events\OrderStatusChanged;
use Modules\Items\Events\OrderUpdated;
use Modules\Items\Events\ReturnRequestCompleted;
use Modules\Wholesale\Listeners\PostB2BOrderEntries;
use Modules\Wholesale\Listeners\PostReturnCredit;
use Modules\Wholesale\Listeners\SyncB2BOrderEntries;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // Merchant finance: invoices and credit notes, inside the order's transaction.
        OrderStatusChanged::class => [PostB2BOrderEntries::class],
        ReturnRequestCompleted::class => [PostReturnCredit::class],
        OrderUpdated::class => [SyncB2BOrderEntries::class],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}
