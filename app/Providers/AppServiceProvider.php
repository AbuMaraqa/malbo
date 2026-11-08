<?php

namespace App\Providers;

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

        // The color and font of the panel or of the store, inlined so the first paint already shows them.
        View::composer('app', function (ViewContract $view): void {
            $admin = HandleInertiaRequests::section(request()) === 'admin';
            $theme = app($admin ? DashboardTheme::class : ThemeService::class);

            $view->with([
                'themeCss' => $theme->css(),
                'fontsUrl' => $theme->fontsUrl() ?? FontCatalog::googleFontsUrl([config('store.default_font')]),
                'faviconUrl' => Storefront::image(app(StoreAppearanceSettings::class)->favicon_path),
            ]);
        });
    }
}
