<?php

declare(strict_types=1);

namespace App\Tenancy\Jobs;

use App\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use App\Tenancy\StorageLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Removes the links made by CreateStorageSymlinks. The store's files are kept, and a
 * path that is not a link is never touched. A link that cannot be removed is reported.
 */
class RemoveStorageSymlinks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant)
    {
    }

    public function handle(): void
    {
        foreach (FilesystemTenancyBootstrapper::urlOverrides($this->tenant) as $path) {
            $link = public_path($path);

            if (StorageLink::isLink($link) && ! StorageLink::remove($link)) {
                report(new RuntimeException("Could not remove the storage link [{$link}] of store [{$this->tenant->getTenantKey()}]."));
            }
        }
    }
}
