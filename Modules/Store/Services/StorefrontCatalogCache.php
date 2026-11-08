<?php

namespace Modules\Store\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The storefront's repeated catalog reads, in the store's own cache. Every key carries a version:
 * a product or category write changes the version, so nothing has to be listed or flushed and old
 * entries expire by themselves. The TTL is only a safety net for writes that skip the model events.
 */
class StorefrontCatalogCache
{
    private const TTL_SECONDS = 900;

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function remember(string $key, Closure $read): mixed
    {
        return Cache::remember(self::key($this->version().'.'.$key), self::TTL_SECONDS, $read);
    }

    /**
     * Now, for the rest of this request, and again after the commit: a visitor reading between the
     * write and its commit would otherwise cache the old rows under the new version.
     */
    public function forget(): void
    {
        $this->bump();

        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->bump());
        }
    }

    private function bump(): void
    {
        Cache::forever(self::key('version'), Str::random(12));
    }

    private function version(): string
    {
        return Cache::rememberForever(self::key('version'), fn () => Str::random(12));
    }

    /** Per store also by key, should the cache store ever be shared between stores. */
    private static function key(string $key): string
    {
        return 'store.catalog.'.(tenant()?->getTenantKey() ?? 'central').'.'.$key;
    }
}
