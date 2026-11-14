<?php

namespace Modules\Items\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Services\BundleService;
use Modules\Items\Services\ItemsPdfService;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ItemsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Items';

    protected string $nameLower = 'items';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();

        $this->app->singleton(ItemsPdfService::class, function () {
            return new ItemsPdfService();
        });

        $this->refreshBundlePrices();
    }

    /**
     * A bundle priced as a percentage off its components follows their regular prices (stored, so
     * the shop can sort and filter by it in SQL): a product or variant whose price or activity
     * changes, that is added, deleted or restored, or a value hidden or shown (which hides or shows
     * its variants), brings those bundles up to date, in the same transaction.
     */
    private function refreshBundlePrices(): void
    {
        $refresh = fn (Product $product) => $product->bundle_type === null
            ? $this->app->make(BundleService::class)->refreshPrices(array_values(array_filter([(int) $product->id, (int) $product->parent_id])))
            : null;

        Product::updated(function (Product $product) use ($refresh): void {
            if ($product->wasChanged(['base_price', 'sale_price', 'is_active'])) {
                $refresh($product);
            }
        });

        foreach (['created', 'deleted', 'restored', 'forceDeleted'] as $event) {
            Product::{$event}($refresh);
        }

        AttributeValue::updated(function (AttributeValue $value): void {
            if ($value->wasChanged('is_active')) {
                $this->app->make(BundleService::class)->refreshForValue((int) $value->id);
            }
        });
    }

    /**
     * Registration the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(SidebarServiceProvider::class);
        $this->app->register(RepositoryServiceProvider::class);
    }

    /**
     * Registration commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([\Modules\Items\Console\RecountSales::class]);
    }

    /**
     * Registration command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Registration translations.
     */
    public function registerTranslations(): void
    {
        $langPath = module_path($this->name, 'Lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, ''), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, ''));
        }
    }

    /**
     * Registration config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $config = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
                    $segments = explode('.', $this->nameLower.'.'.$config_key);

                    // Remove duplicated adjacent segments
                    $normalized = [];
                    foreach ($segments as $segment) {
                        if (end($normalized) !== $segment) {
                            $normalized[] = $segment;
                        }
                    }

                    $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

                    $this->publishes([$file->getPathname() => config_path($config)], 'config');
                    $this->merge_config_from($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Merge config from the given path recursively.
     */
    protected function merge_config_from(string $path, string $key): void
    {
        $existing = config($key, []);
        $module_config = require $path;

        config([$key => array_replace_recursive($existing, $module_config)]);
    }

    /**
     * Registration views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\' . $this->name . '\\View\\Components', $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
