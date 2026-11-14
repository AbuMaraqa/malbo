<?php

namespace Modules\Wholesale\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Items\Contracts\OnAccountPolicy;
use Modules\Items\Contracts\OrderLinePricer;
use Modules\Items\Support\ProductRowLinks;
use Modules\Wholesale\Http\Middleware\EnsureApprovedMerchant;
use Modules\Wholesale\Http\Middleware\EnsureMerchantProfile;
use Modules\Wholesale\Listeners\SendMerchantsToThePortal;
use Modules\Wholesale\Services\Demo\WholesaleDemo;
use Modules\Wholesale\Services\WholesaleOnAccountPolicy;
use Modules\Wholesale\Services\WholesaleOrderLinePricer;
use Nwidart\Modules\Traits\PathNamespace;

/**
 * The wholesale portal: merchants, price tiers, the wholesale cart and checkout. Depends on Core,
 * Items and Store; they never depend on it. Its portal routes are named `store.wholesale.*`, so they
 * are storefront pages (layout, SSR, routes and the guests' sign-in), and are registered before
 * Store's catch-all (module priority -1).
 */
class WholesaleServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Wholesale';

    protected string $nameLower = 'wholesale';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->loadViewsFrom(module_path($this->name, 'resources/views'), $this->nameLower);
        $this->registerRateLimits();

        $this->app->make(Router::class)->aliasMiddleware('merchant.approved', EnsureApprovedMerchant::class);
        $this->app->make(Router::class)->aliasMiddleware('merchant.profile', EnsureMerchantProfile::class);

        // Merchants who sign in on the storefront land in the portal.
        Event::listen(Login::class, SendMerchantsToThePortal::class);

        // The admin's product list links each product to its wholesale prices.
        $this->app->make(ProductRowLinks::class)->add('Wholesale prices', 'wholesale-prices.edit', 'Wholesale Price Update');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), $this->nameLower);

        // Staff editing a wholesale order price its new lines at the merchant's prices.
        $this->app->bind(OrderLinePricer::class, WholesaleOrderLinePricer::class);

        // Staff may put a merchant's wholesale order on account, within its credit.
        $this->app->bind(OnAccountPolicy::class, WholesaleOnAccountPolicy::class);

        // `php artisan malbo:demo`: the demo store's merchants, tiers and wholesale orders.
        $this->app->tag([WholesaleDemo::class], 'malbo.demo');

        $this->app->register(RepositoryServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(SidebarServiceProvider::class);
    }

    /**
     * The join form's attempts per address (successful applications are limited on their own), and
     * checkout: a few orders a minute per merchant.
     */
    private function registerRateLimits(): void
    {
        $answer = fn (Request $request, string $field) => fn () => back()
            ->withInput($request->except(['password', 'password_confirmation', 'register_document']))
            ->withErrors([$field => __('Too many attempts. Try again later.')]);

        // Every attempt, mistakes included: generous, so a shop fixing its form is never locked out.
        // Applications that succeed have their own, tighter limit (MerchantApplicationService).
        RateLimiter::for('wholesale-apply', fn (Request $request) => Limit::perHour(20)
            ->by('wholesale-apply-attempts|'.$request->ip())
            ->response($answer($request, 'email')));

        // Making a PDF catalog or a share link: a few an hour per merchant (each draws up to 200 products).
        RateLimiter::for('wholesale-catalog', fn (Request $request) => Limit::perHour(10)
            ->by('wholesale-catalog|'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(fn () => back()->with('error', __('Too many catalogs made in an hour. Try again later.'))));

        // A statement PDF: a few an hour per merchant.
        RateLimiter::for('wholesale-statement', fn (Request $request) => Limit::perHour(10)
            ->by('wholesale-statement|'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(fn () => back()->with('error', __('Too many statements in an hour. Try again later.'))));

        // A shared catalog link opened by anyone: per address, and per link.
        RateLimiter::for('wholesale-catalog-shared', fn (Request $request) => [
            Limit::perHour(60)->by('wholesale-catalog-shared|'.$request->ip()),
            // And per link: one forwarded link opened by many.
            Limit::perHour(30)->by('wholesale-catalog-shared-token|'.(string) $request->route('token')),
        ]);

        RateLimiter::for('wholesale-checkout', fn (Request $request) => Limit::perMinute(6)
            ->by('wholesale-checkout|'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response($answer($request, 'checkout')));
    }

    public function registerTranslations(): void
    {
        $langPath = module_path($this->name, 'Lang');

        $this->loadTranslationsFrom($langPath, $this->nameLower);
        $this->loadJsonTranslationsFrom($langPath);
    }

    public function provides(): array
    {
        return [];
    }
}
