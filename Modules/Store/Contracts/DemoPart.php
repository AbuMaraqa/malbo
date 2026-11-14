<?php

namespace Modules\Store\Contracts;

use Modules\Store\Services\Demo\DemoStore;

/**
 * A module's share of the demo store (`php artisan malbo:demo`): it runs after the storefront's
 * catalog, shipping and B2C orders exist, writes through its own services, and records what it
 * made on the DemoStore (logins included). Bound with the `malbo.demo` container tag.
 */
interface DemoPart
{
    public function build(DemoStore $demo): void;
}
