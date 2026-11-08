<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\ActivityLogController;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Controllers\Api\V1\Auth\RegisterController;
use Modules\Core\Http\Controllers\Api\V1\CurrencyController;
use Modules\Core\Http\Controllers\Api\V1\SyncController;
use Modules\Core\Http\Controllers\Api\V1\UserController;
use Modules\Core\Http\Controllers\Api\V1\ConfigController;

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::prefix('auth')->as('auth.')->group(function () {
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:api-auth')->name('login');
        Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:api-auth')->name('register');
    });

    Route::controller(ConfigController::class)->prefix('config')->as('config.')->group(function () {
        Route::get('/', 'index')->name('index');
    });

    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('auth')->as('auth.')->group(function () {
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        });

        // Show and update also allow the signed-in user on their own record: see UserController.
        Route::controller(UserController::class)->prefix('users')->as('user.')->group(function () {
            Route::get('/', 'index')->name('index')->can('User View List');
            Route::get('/{user}', 'show')->whereNumber('user')->name('show');
            Route::patch('/{user}', 'update')->whereNumber('user')->name('update');
            Route::delete('/{user}', 'destroy')->whereNumber('user')->name('delete')->can('User Delete');
        });

        // There is no activity log permission or page: the same people who manage roles may read it.
        Route::controller(ActivityLogController::class)->prefix('activity-logs')->as('activity-logs.')->group(function () {
            Route::get('/', 'index')->name('index')->can('Roles And Permissions View List');
            Route::get('/{activity}', 'show')->whereNumber('activity')->name('show')->can('Roles And Permissions View List');
        });

        Route::controller(CurrencyController::class)->prefix('currencies')->as('currencies.')->group(function () {
            Route::get('/', 'index')->name('index')->can('Currency View List');
            Route::get('/{currency}', 'show')->whereNumber('currency')->name('show')->can('Currency View List');
        });

        Route::controller(SyncController::class)->prefix('sync')->as('sync.')->group(function () {
            Route::get('/users', 'syncUsers')->name('users')->can('User View List');
            Route::get('/roles', 'syncRoles')->name('roles')->can('Roles And Permissions View List');
        });
    });

});
