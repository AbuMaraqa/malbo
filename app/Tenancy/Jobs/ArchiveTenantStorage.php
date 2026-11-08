<?php

declare(strict_types=1);

namespace App\Tenancy\Jobs;

use App\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Moves (never deletes) a deleted store's storage/tenant{id} to
 * storage/deleted-stores/tenant{id}-{Ymd_His}, so a store created later with the same
 * id starts empty instead of inheriting the old files.
 *
 * Skipped for stores whose internal `imported` flag is set (their storage was brought in
 * from an existing installation and is never moved). A missing folder is a no-op; a
 * folder that cannot be moved is reported, not thrown (the store is already deleted).
 */
class ArchiveTenantStorage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ARCHIVE_DIRECTORY = 'deleted-stores';

    public function __construct(protected Tenant $tenant)
    {
    }

    public function handle(): void
    {
        if ($this->tenant->getInternal('imported')) {
            return;
        }

        $bootstrapper = app(FilesystemTenancyBootstrapper::class);
        $source = $bootstrapper->tenantStoragePath($this->tenant);

        clearstatcache(true, $source);

        if (! is_dir($source)) {
            return;
        }

        $archive = $bootstrapper->centralStoragePath().'/'.self::ARCHIVE_DIRECTORY;
        app('files')->ensureDirectoryExists($archive);

        $destination = $base = $archive.'/'.basename($source).'-'.now()->format('Ymd_His');
        for ($i = 2; file_exists($destination); $i++) {
            $destination = "{$base}-{$i}";
        }

        if (! @rename($source, $destination)) {
            report(new RuntimeException(
                "Could not move the storage of deleted store [{$this->tenant->getTenantKey()}] from [{$source}] to [{$destination}]."
            ));
        }
    }
}
