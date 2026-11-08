<?php

declare(strict_types=1);

namespace App\Tenancy\Jobs;

use Stancl\Tenancy\Jobs\SeedDatabase;

/**
 * SeedDatabase, except for imported stores: an existing database keeps its data.
 */
class SeedStoreDatabase extends SeedDatabase
{
    public function handle()
    {
        if ($this->tenant->getInternal('imported') === true) {
            return;
        }

        parent::handle();
    }
}
