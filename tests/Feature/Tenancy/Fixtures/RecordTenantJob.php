<?php

namespace Tests\Feature\Tenancy\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Records, in the cache of whatever tenant it runs in, the id of that tenant.
 */
class RecordTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        Cache::put('job-tenant', tenant('id') ?? 'central', 60);
    }
}
