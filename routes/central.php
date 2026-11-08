<?php

use App\Http\Controllers\Platform\LoginController;
use App\Http\Controllers\Platform\LogoutController;
use App\Http\Controllers\Platform\SignUpController;
use App\Http\Controllers\Platform\StoreController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central routes
|--------------------------------------------------------------------------
|
| Platform-only routes. bootstrap/app.php loads this file with `web` once per central domain,
| bound to that domain. Names are prefixed `central.` on the first central domain, the one URLs
| are generated for. These must not reuse a URI of a store route: routes match in registration
| order whatever their domain.
|
*/

Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::get('/register-store', [SignUpController::class, 'create'])->name('signup');
    Route::post('/register-store', [SignUpController::class, 'store'])->middleware('throttle:platform-signup')->name('signup.store');
    Route::get('/register-store/slug', [SignUpController::class, 'slug'])->middleware('throttle:60,1')->name('signup.slug');

    Route::prefix('platform')->name('platform.')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1')->name('login.store');

        Route::middleware('auth:platform')->group(function () {
            Route::post('/logout', LogoutController::class)->name('logout');
            Route::get('/', [StoreController::class, 'index'])->name('dashboard');
            Route::patch('/stores/{store}/status', [StoreController::class, 'status'])->name('stores.status');
            Route::post('/stores/{store}/open-admin', [StoreController::class, 'openAdmin'])->name('stores.open-admin');
            Route::delete('/stores/{store}', [StoreController::class, 'destroy'])->name('stores.destroy');
        });
    });
});
