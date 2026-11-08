<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Gives each store its own cache key prefix. A prefix costs nothing per call, unlike cache tags,
 * which add Redis round trips to every read. Never call Cache::flush() in store code: Redis
 * flushes the whole database, whatever the prefix. Forget keys one by one.
 */
class RedisCacheTenancyBootstrapper implements TenancyBootstrapper
{
    private ?string $originalPrefix = null;

    public function __construct(
        private readonly CacheManager $cache,
        private readonly Repository $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalPrefix ??= (string) $this->config->get('cache.prefix');

        $this->usePrefix($this->originalPrefix.'store_'.$tenant->getTenantKey().'_');
    }

    public function revert(): void
    {
        if ($this->originalPrefix !== null) {
            $this->usePrefix($this->originalPrefix);
        }
    }

    private function usePrefix(string $prefix): void
    {
        $this->config->set('cache.prefix', $prefix);
        $store = $this->config->get('cache.default');

        $this->cache->forgetDriver($store);

        // Repositories the container keeps on its own (see CacheManager::memo and the psr6 binding).
        app()->forgetInstance("cache.__memoized:{$store}");
        app()->forgetInstance('cache.store');
        app()->forgetInstance('cache.psr6');
    }
}
