<?php

namespace App\Providers;

use App\Http\Controllers\Platform\SignUpController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\HandleInertiaRequests;
use Modules\Core\Support\DashboardTheme;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Support\Storefront;
use Modules\Store\Theme\FontCatalog;
use Modules\Store\Theme\ThemeService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // N+1 queries are reported while developing; production is never interrupted by them.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('Lazy loading '.$model::class.'::'.$relation);
        });

        // The API's sign-in and sign-up, per address.
        RateLimiter::for('api-auth', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // New stores per address. Only a created store counts, so fixing a form error is never punished.
        RateLimiter::for('platform-signup', fn (Request $request) => Limit::perHour(3)
            ->by($request->ip())
            ->after(fn ($response) => $response->headers->has(SignUpController::CREATED_HEADER))
            ->response(fn (Request $request, array $headers) => back()->withErrors([
                'provisioning' => __('Too many stores were created from this connection. Try again in :minutes minutes.', [
                    'minutes' => (int) ceil(((int) ($headers['Retry-After'] ?? 3600)) / 60),
                ]),
            ])));

        // The color and font of the panel or of the store, inlined so the first paint already shows them.
        View::composer('app', function (ViewContract $view): void {
            $store = tenancy()->initialized;
            $admin = $store && HandleInertiaRequests::section(request()) === 'admin';
            $theme = $store ? app($admin ? DashboardTheme::class : ThemeService::class) : null;

            $view->with([
                'themeCss' => $theme?->css(),
                'fontsUrl' => $theme?->fontsUrl() ?? FontCatalog::googleFontsUrl([config('store.default_font')]),
                'faviconUrl' => $store ? Storefront::image(app(StoreAppearanceSettings::class)->favicon_path) : null,
            ]);
        });
    }
}
