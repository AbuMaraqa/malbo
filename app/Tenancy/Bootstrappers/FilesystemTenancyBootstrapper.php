<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper as BaseFilesystemTenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Per-store storage, replacing stancl's disk handling:
 *
 * - storage_path() becomes storage/tenant{id} (as in stancl).
 * - Every disk with the `local` driver whose root lies under the central storage path is
 *   tenant scoped automatically (no list to maintain): its root moves to the same
 *   relative path under storage/tenant{id}. So `items` (storage/app/public/items) becomes
 *   storage/tenant{id}/app/public/items.
 * - A scoped disk under app/public gets the URL url('{public prefix}/{sub path}'), where the
 *   prefix is `tenancy.filesystem.url_override.public` with %tenant% replaced
 *   (public-{id}). Other scoped disks get no URL. CreateStorageSymlinks links
 *   public/public-{id} to storage/tenant{id}/app/public, which serves all of them.
 *
 * The disk config is captured once, when the bootstrapper is first built (always in the
 * central context, before the first bootstrap), and restored on revert().
 */
class FilesystemTenancyBootstrapper extends BaseFilesystemTenancyBootstrapper
{
    /** @var array<string, array<string, mixed>> the central config of every disk */
    protected array $originalDisks;

    /** @var array<string, array{relative: string, public: string|null}> scoped disk => its path relative to storage, and to app/public */
    protected array $scopedDisks;

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->originalDisks = (array) $app['config']['filesystems.disks'];
        $this->scopedDisks = $this->deriveScopedDisks();
    }

    public function bootstrap(Tenant $tenant)
    {
        // storage_path()
        if ($this->app['config']['tenancy.filesystem.suffix_storage_path'] ?? true) {
            $this->app->useStoragePath($this->tenantStoragePath($tenant));
        }

        // asset() (off in this app: tenancy.filesystem.asset_helper_tenancy = false)
        if ($this->app['config']['tenancy.filesystem.asset_helper_tenancy'] ?? true) {
            $suffix = $this->app['config']['tenancy.filesystem.suffix_base'].$tenant->getTenantKey();

            if ($this->originalPaths['asset_url']) {
                $this->app['config']['app.asset_url'] = $this->originalPaths['asset_url']."/{$suffix}";
                $this->app['url']->setAssetRoot($this->app['config']['app.asset_url']);
            } else {
                $this->app['url']->setAssetRoot($this->app['url']->route('stancl.tenancy.asset', ['path' => '']));
            }
        }

        // Storage facade
        Storage::forgetDisk(array_keys($this->scopedDisks));

        foreach ($this->scopedDisks as $disk => $scope) {
            $this->app['config']["filesystems.disks.{$disk}.root"] = $this->tenantDiskRoot($tenant, $disk);
            $this->app['config']["filesystems.disks.{$disk}.url"] = $scope['public'] === null
                ? null
                : url(static::publicUrlPrefix($tenant).($scope['public'] === '' ? '' : '/'.$scope['public']));
        }
    }

    public function revert()
    {
        $this->app->useStoragePath($this->originalPaths['storage']);

        $this->app['config']['app.asset_url'] = $this->originalPaths['asset_url'];
        $this->app['url']->setAssetRoot($this->app['config']['app.asset_url']);

        Storage::forgetDisk(array_keys($this->scopedDisks));

        foreach (array_keys($this->scopedDisks) as $disk) {
            $this->app['config']["filesystems.disks.{$disk}"] = $this->originalDisks[$disk];
        }
    }

    /**
     * Names of the tenant-scoped disks.
     *
     * @return list<string>
     */
    public function scopedDisks(): array
    {
        return array_keys($this->scopedDisks);
    }

    /** The storage path outside any store. */
    public function centralStoragePath(): string
    {
        return $this->originalPaths['storage'];
    }

    /** storage/tenant{id}: everything the store keeps on local disks. */
    public function tenantStoragePath(Tenant $tenant): string
    {
        return $this->originalPaths['storage'].'/'
            .$this->app['config']['tenancy.filesystem.suffix_base'].$tenant->getTenantKey();
    }

    /**
     * The root a disk has inside the given store, computed without initializing tenancy
     * (callable from any context). Disks that are not scoped keep their central root.
     */
    public function tenantDiskRoot(Tenant $tenant, string $disk): ?string
    {
        if (! isset($this->scopedDisks[$disk])) {
            return $this->originalDisks[$disk]['root'] ?? null;
        }

        $relative = $this->scopedDisks[$disk]['relative'];

        return $this->tenantStoragePath($tenant).($relative === '' ? '' : '/'.$relative);
    }

    /** public-{id}: the web path that serves storage/tenant{id}/app/public. */
    public static function publicUrlPrefix(Tenant $tenant): string
    {
        return static::urlOverrides($tenant)['public'] ?? 'public-'.$tenant->getTenantKey();
    }

    /**
     * The tenant's web path of every disk in `tenancy.filesystem.url_override`, e.g.
     * ['public' => 'public-acme']. CreateStorageSymlinks links each of them.
     *
     * @return array<string, string>
     */
    public static function urlOverrides(Tenant $tenant): array
    {
        $overrides = [];

        foreach ((array) config('tenancy.filesystem.url_override', []) as $disk => $path) {
            $overrides[$disk] = trim(str_replace('%tenant%', (string) $tenant->getTenantKey(), (string) $path), '/');
        }

        return $overrides;
    }

    /**
     * @return array<string, array{relative: string, public: string|null}>
     */
    protected function deriveScopedDisks(): array
    {
        $storage = $this->normalize($this->originalPaths['storage']);
        $appPublic = $storage.'/app/public';
        $scoped = [];

        foreach ($this->originalDisks as $name => $disk) {
            $root = $disk['root'] ?? null;

            if (($disk['driver'] ?? null) !== 'local' || ! is_string($root) || $root === '') {
                continue;
            }

            $root = $this->normalize($root);

            if (! $this->isWithin($root, $storage)) {
                continue;
            }

            $scoped[$name] = [
                'relative' => ltrim(substr($root, strlen($storage)), '/'),
                'public' => $this->isWithin($root, $appPublic) ? ltrim(substr($root, strlen($appPublic)), '/') : null,
            ];
        }

        return $scoped;
    }

    protected function isWithin(string $path, string $base): bool
    {
        if (windows_os()) {
            [$path, $base] = [strtolower($path), strtolower($base)];
        }

        return $path === $base || str_starts_with($path, $base.'/');
    }

    protected function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
