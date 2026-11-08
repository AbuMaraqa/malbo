<?php

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\CurrencyController;
use Modules\Core\Http\Controllers\Admin\DashboardController;
use Modules\Core\Http\Controllers\Admin\NotificationController;
use Modules\Core\Http\Controllers\Admin\ProfileController;
use Modules\Core\Http\Controllers\Admin\RoleController;
use Modules\Core\Http\Controllers\Admin\SettingController;
use Modules\Core\Http\Controllers\Admin\TwoFactorController;
use Modules\Core\Http\Controllers\Admin\UserController;

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin')->middleware('staff')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings')->can('Setting View List');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update')->can('Setting Update');

        Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/', 'update')->name('update');
            Route::put('/password', 'password')->name('password');
        });

        Route::prefix('profile/two-factor')->name('profile.two-factor.')->controller(TwoFactorController::class)->middleware('throttle:10,1')->group(function () {
            Route::post('/', 'enable')->name('enable');
            Route::post('/confirm', 'confirm')->name('confirm');
            Route::delete('/', 'disable')->name('disable');
            Route::post('/recovery-codes', 'recoveryCodes')->name('recovery-codes');
        });

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read-one');

        Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Roles And Permissions View List');
            Route::post('/', 'store')->name('store')->can('Roles And Permissions Create');
            Route::put('/{role}', 'update')->whereNumber('role')->name('update')->can('Roles And Permissions Update');
            Route::delete('/{role}', 'destroy')->whereNumber('role')->name('destroy')->can('Roles And Permissions Delete');
        });

        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('User View List');
            Route::get('/add', 'add')->name('add')->can('User Create');
            Route::post('/', 'store')->name('store')->can('User Create');
            Route::get('/{user}/edit', 'edit')->whereNumber('user')->name('edit')->can('User Update');
            Route::put('/{user}', 'update')->whereNumber('user')->name('update')->can('User Update');
            Route::delete('/{user}', 'destroy')->whereNumber('user')->name('destroy')->can('User Delete');
        });

        Route::prefix('currencies')->name('currencies.')->controller(CurrencyController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Currency View List');
            Route::get('/add', 'add')->name('add')->can('Currency Create');
            Route::post('/', 'store')->name('store')->can('Currency Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Currency Delete');
            Route::get('/{currency}/edit', 'edit')->whereNumber('currency')->name('edit')->can('Currency Update');
            Route::put('/{currency}', 'update')->whereNumber('currency')->name('update')->can('Currency Update');
            Route::delete('/{currency}', 'destroy')->whereNumber('currency')->name('destroy')->can('Currency Delete');
        });
    });
});

// URLs without a locale (/admin/products) go to /{locale}/admin/products when that exists.
Route::fallback(function (Request $request) {
    // An API client gets a JSON 404, never a redirect to a storefront page.
    abort_if($request->is('api/*'), 404);

    $locale = session('locale');
    $locale = in_array($locale, Locales::keys(), true) ? $locale : Locales::default();
    $path = rtrim('/'.$locale.'/'.ltrim($request->path(), '/'), '/');

    try {
        $target = Route::getRoutes()->match(Request::create($request->getSchemeAndHttpHost().$path, 'GET'));
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
        abort(404);
    }

    abort_if($target->isFallback, 404);

    return redirect($path.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
});
