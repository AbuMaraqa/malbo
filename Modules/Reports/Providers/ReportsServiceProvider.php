<?php

namespace Modules\Reports\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;

/**
 * Read-only reports over orders, returns, carts and merchants. Depends on Core, Items, Store and
 * Wholesale; nothing depends on it. It writes nothing.
 */
class ReportsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Reports';

    protected string $nameLower = 'reports';

    public function boot(): void
    {
        $langPath = module_path($this->name, 'Lang');

        $this->loadTranslationsFrom($langPath, $this->nameLower);
        $this->loadJsonTranslationsFrom($langPath);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), $this->nameLower);

        $this->app->register(RepositoryServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(SidebarServiceProvider::class);
    }

    public function provides(): array
    {
        return [];
    }
}
