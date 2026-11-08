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
use Throwable;

/**
 * Links public/{url_override} (e.g. public/public-acme) to the store's disk root, so the
 * tenant-prefixed disk URLs are served by the web server.
 *
 * A link that cannot be created is reported, never thrown: the store stays usable and
 * the link can be created again by re-running this job. On Windows Laravel creates a
 * directory junction (mklink /J), which needs no administrator rights.
 */
class CreateStorageSymlinks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant)
    {
    }

    public function handle(): void
    {
        $bootstrapper = app(FilesystemTenancyBootstrapper::class);

        foreach (FilesystemTenancyBootstrapper::urlOverrides($this->tenant) as $disk => $path) {
            $link = public_path($path);
            $target = $this->normalize((string) $bootstrapper->tenantDiskRoot($this->tenant, $disk));

            try {
                if ($target === '') {
                    throw new RuntimeException("the disk [{$disk}] has no root");
                }

                $this->link($target, $link);
            } catch (Throwable $e) {
                report(new RuntimeException(
                    "Could not link [{$link}] to [{$target}] for store [{$this->tenant->getTenantKey()}]: {$e->getMessage()}",
                    previous: $e,
                ));
            }
        }
    }

    protected function link(string $target, string $link): void
    {
        clearstatcache(true, $link);

        if (StorageLink::isLink($link)) {
            return; // already linked (e.g. a retried pipeline)
        }

        if (file_exists($link)) {
            throw new RuntimeException('the path exists and is not a link');
        }

        $files = app('files');
        $files->ensureDirectoryExists($target); // a junction needs an existing target directory
        $files->link($target, $link);

        clearstatcache(true, $link);

        if (! StorageLink::isLink($link)) {
            // On Windows mklink only reports failures on its output; nothing is thrown.
            throw new RuntimeException('the link was not created (are symlinks permitted?)');
        }
    }

    protected function normalize(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
