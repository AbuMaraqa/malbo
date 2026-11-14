<?php

namespace Modules\Items\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Items\Events\OrderStatusChanged;
use Modules\Items\Listeners\CancelPendingShipment;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // Synchronous: the shipment is cancelled in the order's own transaction.
        OrderStatusChanged::class => [CancelPendingShipment::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
