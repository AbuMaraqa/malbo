<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\Category;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Tenancy\Fixtures\RecordTenantJob;
use Tests\TenantTestCase;

/**
 * Every test here switches stores, so it runs without the tenant transaction and
 * removes what it wrote (rows, cache keys, files, permissions) in tearDown().
 */
class TenantIsolationTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    private const CATEGORY_SLUG = 'tenant-isolation-probe';

    private const PERMISSION = 'Only In Testing';

    private const CACHE_KEYS = ['k', 'job-tenant'];

    public function test_eloquent_data_is_isolated(): void
    {
        Category::create(['slug' => self::CATEGORY_SLUG]); // in `testing`

        $this->secondTenant()->run(function () {
            $this->assertSame('other', tenant('id'));
            $this->assertFalse(Category::where('slug', self::CATEGORY_SLUG)->exists());
        });

        $this->assertTrue(Category::where('slug', self::CATEGORY_SLUG)->exists());
    }

    public function test_cache_is_isolated_and_survives_switching_back(): void
    {
        Cache::put('k', 'testing-value', 60);

        $this->secondTenant()->run(function () {
            $this->assertNull(Cache::get('k'));
            Cache::put('k', 'other-value', 60);
            $this->assertSame('other-value', Cache::get('k'));
        });

        // testing -> other -> testing: the store re-resolves on the testing connection.
        $this->assertSame('testing-value', Cache::get('k'));
        $this->assertTrue($this->cachedUnderStore('testing', 'k'));
        $this->assertSame(config('cache.prefix'), $this->storePrefix('testing'));

        tenancy()->end();
        $this->assertNull(Cache::get('k')); // central cache table is separate
        $this->assertSame(config('database.connections.central.database'), DB::connection()->getDatabaseName());

        // central -> testing again.
        tenancy()->initialize($this->tenant);
        $this->assertSame('testing-value', Cache::get('k'));
    }

    public function test_cache_locks_are_isolated(): void
    {
        $lock = Cache::lock('isolation-lock', 30);
        $this->assertTrue($lock->get());

        try {
            $this->secondTenant()->run(function () {
                $otherLock = Cache::lock('isolation-lock', 30);
                $this->assertTrue($otherLock->get(), 'A lock held in testing must not block other.');
                $otherLock->release();
            });

            $this->assertFalse(Cache::lock('isolation-lock', 30)->get(), 'The testing lock must still be held.');
        } finally {
            $lock->release();
        }
    }

    public function test_permission_cache_key_is_per_tenant(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $this->assertSame('spatie.permission.cache.tenant.testing', $registrar->cacheKey);

        Permission::findOrCreate(self::PERMISSION);
        // Load the collection into memory so a leak across stores would show.
        $this->assertTrue($registrar->getPermissions()->contains('name', self::PERMISSION));

        $otherKey = 'spatie.permission.cache.tenant.other';

        $this->secondTenant()->run(function () use ($otherKey) {
            $registrar = app(PermissionRegistrar::class);
            $this->assertSame('spatie.permission.cache.tenant.other', $registrar->cacheKey);
            $this->assertFalse($registrar->getPermissions()->contains('name', self::PERMISSION));

            // The warmed cache entry was written under other's own cache prefix (a stale
            // store would have written it under testing's).
            $this->assertSame(config('tenancy.database.prefix').'other', DB::connection('tenant')->getDatabaseName());
            $this->assertTrue($this->cachedUnderStore('other', $otherKey));
        });

        $this->assertSame(config('tenancy.database.prefix').'testing', DB::connection('tenant')->getDatabaseName());
        $this->assertFalse($this->cachedUnderStore('testing', $otherKey));
        $this->assertTrue($registrar->getPermissions()->contains('name', self::PERMISSION));

        tenancy()->end();
        $this->assertSame('spatie.permission.cache', $registrar->cacheKey);
    }

    public function test_public_disk_is_isolated_and_urls_are_tenant_prefixed(): void
    {
        Storage::disk('public')->put('probe.txt', 'x');

        $this->assertStringContainsString('/public-testing/probe.txt', Storage::disk('public')->url('probe.txt'));
        $this->assertStringContainsString('tenanttesting', Storage::disk('public')->path('probe.txt'));

        $this->secondTenant()->run(function () {
            $this->assertFalse(Storage::disk('public')->exists('probe.txt'));
            $this->assertStringContainsString('/public-other/probe.txt', Storage::disk('public')->url('probe.txt'));
        });

        $this->assertTrue(Storage::disk('public')->exists('probe.txt'));

        tenancy()->end();
        $this->assertStringContainsString('/storage/probe.txt', Storage::disk('public')->url('probe.txt'));
        $this->assertStringNotContainsString('tenant', Storage::disk('public')->path('probe.txt'));
    }

    public function test_local_disk_is_isolated(): void
    {
        Storage::disk('local')->put('probe.txt', 'x');
        $this->assertStringContainsString('tenanttesting', Storage::disk('local')->path('probe.txt'));

        $this->secondTenant()->run(fn () => $this->assertFalse(Storage::disk('local')->exists('probe.txt')));
    }

    public function test_every_local_disk_under_storage_is_tenant_scoped(): void
    {
        $scoped = app(FilesystemTenancyBootstrapper::class)->scopedDisks();

        // Derived from the disk config, not a list: the app's media disks are all in it.
        foreach (['local', 'public', 'media', 'items', 'customers', 'content', 'banners', 'offers', 'addon_option', 'companies'] as $disk) {
            $this->assertContains($disk, $scoped);
        }
        $this->assertNotContains('s3', $scoped);

        tenancy()->end();
        $storage = $this->normalize(storage_path());
        $centralRoots = array_map(fn (string $disk) => $this->normalize(config("filesystems.disks.{$disk}.root")), array_combine($scoped, $scoped));
        $centralUrls = array_map(fn (string $disk) => config("filesystems.disks.{$disk}.url"), array_combine($scoped, $scoped));
        tenancy()->initialize($this->tenant);

        foreach ($scoped as $disk) {
            $relative = substr($centralRoots[$disk], strlen($storage)); // e.g. /app/public/items

            $this->assertSame(
                $storage.'/tenanttesting'.$relative.'/probe.txt',
                $this->normalize(Storage::disk($disk)->path('probe.txt')),
                "disk [{$disk}] root",
            );

            if (str_starts_with($relative, '/app/public')) {
                $this->assertSame(url('public-testing'.substr($relative, strlen('/app/public')).'/probe.txt'), Storage::disk($disk)->url('probe.txt'), "disk [{$disk}] url");
            } else {
                $this->assertNull(config("filesystems.disks.{$disk}.url"), "disk [{$disk}] must not get a public url");
            }
        }

        $this->assertStringContainsString('/public-testing/items/probe.txt', Storage::disk('items')->url('probe.txt'));
        $this->assertStringContainsString('/public-testing/items/offers/probe.txt', Storage::disk('offers')->url('probe.txt'));
        $this->assertStringContainsString('/public-testing/media/probe.txt', Storage::disk('media')->url('probe.txt'));

        $this->secondTenant()->run(function () {
            $this->assertStringContainsString('tenantother', Storage::disk('items')->path('probe.txt'));
            $this->assertStringContainsString('/public-other/items/probe.txt', Storage::disk('items')->url('probe.txt'));
        });

        // Central context gets the original roots and URLs back. (Config only: resolving a
        // local disk creates its root directory in the real central storage.)
        tenancy()->end();
        foreach ($scoped as $disk) {
            $this->assertSame($centralRoots[$disk], $this->normalize(config("filesystems.disks.{$disk}.root")));
            $this->assertSame($centralUrls[$disk], config("filesystems.disks.{$disk}.url"));
            $this->assertStringNotContainsString('tenant', config("filesystems.disks.{$disk}.root"));
        }
        $this->assertStringEndsWith('/storage/items', config('filesystems.disks.items.url'));
    }

    public function test_media_folders_do_not_collide_across_stores(): void
    {
        // Medialibrary stores media id 1 as "1/{file}" in both stores.
        Storage::disk('items')->put('1/x.jpg', 'testing-file');

        $this->secondTenant()->run(function () {
            $this->assertFalse(Storage::disk('items')->exists('1/x.jpg'));
            Storage::disk('items')->put('1/x.jpg', 'other-file');
            $this->assertSame('other-file', Storage::disk('items')->get('1/x.jpg'));
        });

        $this->assertSame('testing-file', Storage::disk('items')->get('1/x.jpg'));

        $this->secondTenant()->run(fn () => Storage::disk('items')->delete('1/x.jpg'));

        $this->assertSame('testing-file', Storage::disk('items')->get('1/x.jpg'));
        $this->secondTenant()->run(fn () => $this->assertFalse(Storage::disk('items')->exists('1/x.jpg')));
    }

    public function test_queued_job_runs_in_the_dispatching_tenant(): void
    {
        // Redis is the queue of this project: one queue shared by every store and the worker.
        config(['queue.default' => 'redis']);
        $queue = 'isolation-'.bin2hex(random_bytes(6));

        RecordTenantJob::dispatch()->onQueue($queue); // writes tenant('id') into the cache key 'job-tenant'

        // The job carries the dispatching store.
        $this->assertSame(1, Queue::connection('redis')->size($queue));
        $payload = json_decode(Redis::connection(config('queue.connections.redis.connection'))->lindex('queues:'.$queue, 0), true);
        $this->assertSame('testing', $payload['tenant_id']);

        tenancy()->end();
        $this->artisan('queue:work', ['connection' => 'redis', '--once' => true, '--queue' => $queue])
            ->assertSuccessful();

        $this->assertSame(0, Queue::connection('redis')->size($queue));
        $this->assertSame(0, DB::connection('central')->table('failed_jobs')->count());
        $this->assertFalse(tenancy()->initialized, 'The worker must leave the store after the job.');
        $this->assertNull(Cache::get('job-tenant'));

        tenancy()->initialize(Tenant::find('testing'));
        $this->assertSame('testing', Cache::get('job-tenant'));
    }

    public function test_settings_are_isolated_across_store_switches(): void
    {
        $stores = ['testing', $this->secondTenant()->getTenantKey()];
        $original = [];

        tenancy()->runForMultiple($stores, function () use (&$original) {
            $original[tenant('id')] = $this->storedSiteName();
        });

        try {
            // save() in one process: each store must write its own value, not the previous store's settings.
            tenancy()->runForMultiple($stores, function () {
                $settings = app(GeneralSettings::class);
                $settings->site_name = 'Isolation '.tenant('id');
                $settings->save();
            });

            tenancy()->runForMultiple($stores, function () {
                $this->assertSame('Isolation '.tenant('id'), app(GeneralSettings::class)->site_name);
                $this->assertSame('Isolation '.tenant('id'), $this->storedSiteName());
            });

            // Back in a store after another one: still its own settings.
            $this->assertSame('Isolation testing', app(GeneralSettings::class)->site_name);
        } finally {
            tenancy()->runForMultiple($stores, function () use ($original) {
                DB::table('settings')->where('group', 'general')->where('name', 'site_name')
                    ->update(['payload' => json_encode($original[tenant('id')])]);
            });
        }
    }

    /**
     * The cache key prefix of a store: the central prefix plus the store's id.
     */
    private function storePrefix(string $store): string
    {
        return Str::before((string) config('cache.prefix'), 'store_').'store_'.$store.'_';
    }

    /**
     * Whether the raw key exists in Redis under that store's prefix, whatever store is current.
     */
    private function cachedUnderStore(string $store, string $key): bool
    {
        return (bool) Cache::getStore()->connection()->exists($this->storePrefix($store).$key);
    }

    private function storedSiteName(): string
    {
        return json_decode(DB::table('settings')->where('group', 'general')->where('name', 'site_name')->value('payload'));
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            $this->cleanUp();
        }

        parent::tearDown();
    }

    private function cleanUp(): void
    {
        tenancy()->initialize($this->tenant);

        Category::withTrashed()->where('slug', self::CATEGORY_SLUG)->get()->each(function (Category $category) {
            $category->forceDelete();
            DB::table('activity_log')
                ->where('subject_type', $category->getMorphClass())
                ->where('subject_id', $category->getKey())
                ->delete();
        });

        Permission::where('name', self::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->deleteProbeFiles();
        Cache::deleteMultiple(self::CACHE_KEYS);

        $this->secondTenant()->run(function () {
            $this->deleteProbeFiles();
            Cache::deleteMultiple(self::CACHE_KEYS);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        tenancy()->end();
        Cache::deleteMultiple(self::CACHE_KEYS);
        DB::connection('central')->table('jobs')->delete();
        DB::connection('central')->table('failed_jobs')->delete();

        tenancy()->initialize($this->tenant);
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function deleteProbeFiles(): void
    {
        foreach (app(FilesystemTenancyBootstrapper::class)->scopedDisks() as $disk) {
            Storage::disk($disk)->delete('probe.txt');
        }

        Storage::disk('items')->delete('1/x.jpg');
        if (Storage::disk('items')->allFiles('1') === []) {
            Storage::disk('items')->deleteDirectory('1');
        }
    }
}
