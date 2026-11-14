<?php

use Modules\Items\Services\Couriers\ManualCourier;

/*
 * The couriers a shipment can go with. `key` is stored on the shipment (never change it once
 * used), `name` is shown in the admin (translated), `gateway` is the class that talks to the
 * courier (Modules\Items\Services\Couriers\CourierGateway).
 *
 * Only the built-in manual courier is bound: staff type the tracking number and move the status
 * by hand. A courier integration adds its own entry, for example:
 *   ['key' => 'example', 'name' => 'Example Express', 'gateway' => App\Couriers\ExampleExpress::class],
 */
return [
    'couriers' => [
        ['key' => 'manual', 'name' => 'Own delivery', 'gateway' => ManualCourier::class],
    ],
];
