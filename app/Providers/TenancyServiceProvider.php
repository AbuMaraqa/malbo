<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\CreatePlatformAdmin;
use App\Console\Commands\CreateStore;
use App\Console\Commands\LinkStoreStorage;
use App\Http\Middleware\EnsureStoreIsActive;
use App\Http\Middleware\InitializeTenancyIfTenantHost;
use App\Tenancy\Jobs\ArchiveTenantStorage;
use App\Tenancy\Jobs\CreateStorageSymlinks;
use App\Tenancy\Jobs\DeleteStoreDatabase;
use App\Tenancy\Jobs\RemoveStorageSymlinks;
use App\Tenancy\Jobs\SeedStoreDatabase;
use App\Tenancy\TenantMigrationPaths;
use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\LaravelSettings\SettingsMapper;
use Spatie\Permission\PermissionRegistrar;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenantCannotBeCreatedException;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;
use Throwable;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                $this->deleteTenantWhenProvisioningFails(
                    JobPipeline::make([
                        Jobs\CreateDatabase::class,
                        Jobs\MigrateDatabase::class,
                        SeedStoreDatabase::class, // skipped for imported stores
                        CreateStorageSymlinks::class, // never throws: a missing link is reported
                    ])->send(function (Events\TenantCreated $event) {
                        return $event->tenant;
                    })->shouldBeQueued(false) // Sync: a store must be usable as soon as sign-up returns.
                ),
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    RemoveStorageSymlinks::class,
                    DeleteStoreDatabase::class, // skipped for imported stores: their database is never dropped
                    ArchiveTenantStorage::class, // moves storage/tenant{id} to storage/deleted-stores
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
                fn (Events\TenancyEnded $event) => $this->scopePermissionCache(null),
                fn (Events\TenancyEnded $event) => $this->forgetResolvedSettings(),
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [
                fn (Events\TenancyBootstrapped $event) => $this->scopePermissionCache($event->tenancy->tenant),
                fn (Events\TenancyBootstrapped $event) => $this->forgetResolvedSettings(),
            ],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    /**
     * Give spatie/permission a cache key per store and drop the permissions it holds in
     * memory, so a long-lived process never serves one store the permissions of another.
     * initializeCache() also re-reads the cache store, which DatabaseCacheTenancyBootstrapper
     * has just re-resolved on the current connection.
     */
    protected function scopePermissionCache(?Tenant $tenant): void
    {
        config(['permission.cache.key' => $tenant
            ? 'spatie.permission.cache.tenant.'.$tenant->getTenantKey()
            : 'spatie.permission.cache']);

        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->initializeCache(); // reads permission.cache.key into $registrar->cacheKey
        $registrar->clearPermissionsCollection();
    }

    /**
     * spatie/laravel-settings binds every settings class (and its SettingsMapper) as `scoped`:
     * resolved once, it would keep serving -- and save() writing -- the values of the store
     * it was loaded in after a switch to another store within the same process
     * (runForMultiple, tenants:run, multi-store jobs). Forget them on every switch.
     */
    protected function forgetResolvedSettings(): void
    {
        foreach ($this->app->make(SettingsContainer::class)->getSettingClasses() as $settingsClass) {
            $this->app->forgetInstance($settingsClass);
        }

        $this->app->forgetInstance(SettingsMapper::class);
    }

    /**
     * Run the provisioning pipeline; if any step throws, restore the previous tenancy
     * context, delete the tenant (TenantDeleted drops its database) and rethrow.
     */
    protected function deleteTenantWhenProvisioningFails(JobPipeline $pipeline): Closure
    {
        $listener = $pipeline->toListener();

        return function (Events\TenantCreated $event) use ($listener) {
            $originalTenant = tenant();

            try {
                $listener($event);
            } catch (Throwable $e) {
                try {
                    if ($originalTenant) {
                        tenancy()->initialize($originalTenant);
                    } else {
                        tenancy()->end();
                    }

                    if ($e instanceof TenantCannotBeCreatedException) {
                        // The database was not created by us (e.g. it already exists): never drop it.
                        $event->tenant->deleteQuietly();
                    } else {
                        $event->tenant->delete();
                    }
                } catch (Throwable $cleanup) {
                    report($cleanup); // never let a failed clean-up hide why provisioning failed
                }

                throw $e;
            }
        };
    }

    public function boot()
    {
        config(['tenancy.migration_parameters.--path' => TenantMigrationPaths::all()]);

        if ($this->app->runningInConsole()) {
            $this->commands([LinkStoreStorage::class, CreateStore::class, CreatePlatformAdmin::class]);
        }

        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            InitializeTenancyIfTenantHost::class,
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,

            // Right after identification: a suspended store answers before auth, session, bindings.
            EnsureStoreIsActive::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
