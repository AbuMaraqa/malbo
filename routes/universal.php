<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\RobotsController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Universal routes
|--------------------------------------------------------------------------
|
| Served on the platform host and on every store host (loaded with `web` by bootstrap/app.php).
| `tenancy.universal` initializes the store on a store host and leaves the request central on a
| platform host.
|
*/

Route::middleware('tenancy.universal')->group(function () {
    Route::get('/', LocaleRedirectController::class);

    // Per host: a store points crawlers at its own sitemap.
    Route::get('/robots.txt', RobotsController::class)->name('robots');

    Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
        // Storefront home on a store host, landing page on the platform host.
        Route::get('/', HomeController::class)->name('home');
    });
});
