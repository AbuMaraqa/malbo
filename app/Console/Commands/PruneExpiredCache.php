<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The cache is the database's `cache` table: an expired entry is only removed when its own key is
 * read again, so keys nobody asks for anymore (old catalog versions) stay as rows. This deletes
 * every row past its expiry; scheduled daily in routes/console.php.
 */
class PruneExpiredCache extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete the database cache entries past their expiry';

    public function handle(): int
    {
        $store = config('cache.stores.database');

        $deleted = DB::connection($store['connection'] ?? null)
            ->table($store['table'] ?? 'cache')
            ->where('expiration', '<=', now()->getTimestamp())
            ->delete();

        $this->info("Expired cache entries deleted: {$deleted}.");

        return self::SUCCESS;
    }
}
