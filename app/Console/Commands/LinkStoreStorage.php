<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use App\Tenancy\Jobs\CreateStorageSymlinks;
use App\Tenancy\StorageLink;
use Illuminate\Console\Command;

/**
 * Creates (again) the public/public-{id} storage links of one store or of every store,
 * e.g. after CreateStorageSymlinks reported a failure during provisioning.
 */
class LinkStoreStorage extends Command
{
    protected $signature = 'stores:link {id? : The store id; every store when omitted}';

    protected $description = 'Create the public storage links of one store or of all stores';

    public function handle(): int
    {
        $id = $this->argument('id');

        if ($id !== null) {
            $tenant = tenancy()->find($id);

            if (! $tenant) {
                $this->components->error("Store [{$id}] not found.");

                return self::FAILURE;
            }

            $tenants = [$tenant];
        } else {
            $tenants = tenancy()->model()->newQuery()->cursor();
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            (new CreateStorageSymlinks($tenant))->handle();

            foreach (FilesystemTenancyBootstrapper::urlOverrides($tenant) as $path) {
                $link = public_path($path);

                if (StorageLink::isLink($link)) {
                    $this->components->info("Store [{$tenant->getTenantKey()}]: linked [{$link}].");
                } else {
                    $this->components->error("Store [{$tenant->getTenantKey()}]: could not link [{$link}] (see the log).");
                    $failed++;
                }
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
