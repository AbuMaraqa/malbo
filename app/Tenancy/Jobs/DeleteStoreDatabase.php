<?php

declare(strict_types=1);

namespace App\Tenancy\Jobs;

use Stancl\Tenancy\Jobs\DeleteDatabase;

/**
 * DeleteDatabase, except for stores whose internal `imported` flag is set: their database
 * existed before the platform (e.g. the original `core`) and is never dropped.
 */
class DeleteStoreDatabase extends DeleteDatabase
{
    public function handle()
    {
        if ($this->tenant->getInternal('imported')) {
            return;
        }

        parent::handle();
    }
}
