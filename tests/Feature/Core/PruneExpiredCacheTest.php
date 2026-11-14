<?php

namespace Tests\Feature\Core;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

/**
 * The cache lives in the database: entries past their expiry stay as rows until something deletes
 * them. `cache:prune-expired` does, daily.
 */
class PruneExpiredCacheTest extends DatabaseTestCase
{
    public function test_it_deletes_expired_rows_only(): void
    {
        $table = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix');
        DB::table($table)->insert([
            ['key' => $prefix.'old', 'value' => 's:1:"a";', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => $prefix.'fresh', 'value' => 's:1:"b";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('cache:prune-expired')->assertSuccessful();

        $this->assertFalse(DB::table($table)->where('key', $prefix.'old')->exists());
        $this->assertTrue(DB::table($table)->where('key', $prefix.'fresh')->exists());
    }

    public function test_it_runs_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'cache:prune-expired'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }
}
