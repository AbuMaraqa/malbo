<?php

namespace Modules\Store\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Store\Entities\Review;
use Modules\Store\Services\ReviewService;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeTranslation;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\AttributeValueTranslation;
use Modules\Items\Entities\Category;
use Modules\Items\Events\ProductSalesChanged;
use Modules\Items\Events\ProductsBecameOrderable;
use Modules\Items\Entities\CategoryTranslation;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\LabelTranslation;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ProductTranslation;
use Modules\Store\Console\BuildDemoStore;
use Modules\Store\Console\SendCartReminders;
use Modules\Store\Contracts\InstagramFeedSource;
use Modules\Store\Contracts\SmsGateway;
use Modules\Store\Support\ManualInstagramFeed;
use Modules\Store\Jobs\SendStockAlerts;
use Modules\Store\Services\Marketing\CatalogFeed;
use Modules\Store\Services\Marketing\MetaEvents;
use Modules\Store\Services\StorefrontCatalogCache;
use Modules\Store\Support\LogSmsGateway;
use Nwidart\Modules\Traits\PathNamespace;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class StoreServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Store';

    protected string $nameLower = 'store';

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
        $this->forgetCatalogOnWrites();
        $this->registerRateLimits();
    }

    /**
     * Checkout: a handful of orders a minute from one address is plenty for a buyer, and stops a
     * script from emptying the stock with fake cash orders.
     */
    private function registerRateLimits(): void
    {
        RateLimiter::for('store-checkout', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        // The header's suggestions: one request a typing pause. Per visitor (address and session), so
        // buyers behind one address (an office, a mobile carrier) do not share it; and per address
        // alone, higher, so a script dropping its cookie still stops.
        RateLimiter::for('store-search', fn (Request $request) => [
            Limit::perMinute(120)->by('store-search:'.$request->ip().'|'.($request->hasSession() ? $request->session()->getId() : '')),
            Limit::perMinute(300)->by('store-search-ip:'.$request->ip()),
        ]);

        // Phase 8A. The contact a buyer types at checkout is saved on blur: a few a minute. "Notify me"
        // and reviews: a handful per address, so a script cannot fill the tables or mail strangers.
        RateLimiter::for('store-cart-contact', fn (Request $request) => Limit::perMinute(20)->by('cart-contact:'.$request->ip()));
        RateLimiter::for('store-stock-alert', fn (Request $request) => [
            Limit::perMinute(5)->by('stock-alert:'.$request->ip()),
            Limit::perHour(30)->by('stock-alert-hour:'.$request->ip()),
        ]);
        RateLimiter::for('store-review', fn (Request $request) => Limit::perHour(10)->by('review:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Sign-up, reset links and return requests: per address and email, as Fortify limits its
        // login; and per address alone, so trying many emails (to learn which have an account)
        // stops too: 10 sign-ups an hour, 5 reset links in 10 minutes. The buyer gets a message on
        // the form, not an error page. Sign-in counts its own failures in CustomerAuthService.
        RateLimiter::for('store-account', function (Request $request) {
            $route = (string) $request->route()?->getName();
            $answer = fn () => back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
                ->withErrors([$request->has('email') ? 'email' : 'returns' => __('Too many attempts. Try again later.')]);

            $limits = [Limit::perMinute(5)->by(implode('|', [$route, Str::transliterate(Str::lower((string) $request->input('email'))), $request->ip()]))->response($answer)];

            match ($route) {
                'store.account.register.store' => $limits[] = Limit::perHour(10)->by('register-ip|'.$request->ip())->response($answer),
                'store.account.password.email' => $limits[] = Limit::perMinutes(10, 5)->by('reset-ip|'.$request->ip())->response($answer),
                default => null,
            };

            return $limits;
        });
    }

    /**
     * Whichever service or import writes a product or category, the storefront's cached lists go;
     * so do label writes, which change the cards' badges, attribute writes (filters, card sizes)
     * and sales counts (best selling).
     */
    private function forgetCatalogOnWrites(): void
    {
        $forget = fn () => $this->app->make(StorefrontCatalogCache::class)->forget();

        foreach ([Product::class, ProductTranslation::class, Category::class, CategoryTranslation::class, Label::class, LabelTranslation::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }

        foreach ([Product::class, Category::class] as $model) {
            $model::restored($forget);
        }

        $forgetImage = function (Media $media) use ($forget): void {
            if (in_array($media->model_type, [(new Product)->getMorphClass(), (new Category)->getMorphClass()], true)) {
                $forget();
            }
        };

        Media::saved($forgetImage);
        Media::deleted($forgetImage);

        // Filters and card sizes read attributes and their values; best selling reads the sales counts.
        foreach ([Attribute::class, AttributeTranslation::class, AttributeValue::class, AttributeValueTranslation::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }

        Event::listen(ProductSalesChanged::class, $forget);

        // The catalog feed has its own hourly cache: only a product that appears, goes, or is published
        // or hidden rebuilds it, never a sale.
        foreach (['created', 'deleted', 'restored'] as $event) {
            Product::{$event}(fn () => CatalogFeed::forget());
        }
        Product::updated(function (Product $product): void {
            if ($product->wasChanged(['status', 'is_active', 'slug', 'sku', 'parent_id'])) {
                CatalogFeed::forget();
            }
        });

        // A product's rating summary is cached (ProductReviews): a review that goes, alone or with its
        // account (a database cascade, which fires no model event), takes its rating with it.
        Review::deleted(fn (Review $review) => Cache::forget(ReviewService::summaryKey((int) $review->product_id)));
        User::deleting(function (User $user): void {
            foreach (Review::query()->where('user_id', $user->id)->distinct()->pluck('product_id') as $productId) {
                Cache::forget(ReviewService::summaryKey((int) $productId));
                DB::afterCommit(fn () => Cache::forget(ReviewService::summaryKey((int) $productId)));
            }
        });

        // Back-in-stock alerts, on the database queue: the person receiving stock never waits.
        Event::listen(ProductsBecameOrderable::class, fn (ProductsBecameOrderable $event) => SendStockAlerts::dispatch($event->productIds));
    }

    /**
     * Registration the service provider.
     */
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(SidebarServiceProvider::class);

        // One per request: it gathers the request's server events and posts them in one call.
        $this->app->scoped(MetaEvents::class);

        // What the storefront shows of Instagram: the posts staff enter. A Graph API sync binds its
        // own source (store.instagram.source), or fills the same table through InstagramService::import.
        $this->app->bind(InstagramFeedSource::class, fn ($app) => match (config('store.instagram.source')) {
            default => $app->make(ManualInstagramFeed::class),
        });

        // Text messages: only the log driver ships; a provider binds its own SmsGateway.
        $this->app->bind(SmsGateway::class, fn () => match (config('store.sms.driver')) {
            default => new LogSmsGateway,
        });
    }

    /**
     * Registration commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([SendCartReminders::class, BuildDemoStore::class]);
    }

    /**
     * Registration command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // Abandoned-cart reminders; the run also holds its own lock (CartReminderSender).
        $this->app->booted(function () {
            $this->app->make(Schedule::class)->command('store:send-cart-reminders')->everyTenMinutes()->withoutOverlapping(15);
        });
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
