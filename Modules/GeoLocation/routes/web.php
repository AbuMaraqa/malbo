<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\GeoLocation\Http\Controllers\Admin\CityController;
use Modules\GeoLocation\Http\Controllers\Admin\CountryController;
use Modules\GeoLocation\Http\Controllers\Admin\DistrictController;
use Modules\GeoLocation\Http\Controllers\Admin\GovernorateController;

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin/geolocation')->middleware('staff')->group(function () {
        Route::prefix('countries')->name('countries.')->controller(CountryController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Country View List');
            Route::get('/add', 'add')->name('add')->can('Country Create');
            Route::post('/', 'store')->name('store')->can('Country Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Country Delete');
            Route::get('/{country}/edit', 'edit')->whereNumber('country')->name('edit')->can('Country Update');
            Route::put('/{country}', 'update')->whereNumber('country')->name('update')->can('Country Update');
            Route::delete('/{country}', 'destroy')->whereNumber('country')->name('destroy')->can('Country Delete');
        });

        Route::prefix('governorates')->name('governorates.')->controller(GovernorateController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Governorate View List');
            Route::get('/add', 'add')->name('add')->can('Governorate Create');
            Route::post('/', 'store')->name('store')->can('Governorate Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Governorate Delete');
            Route::get('/{governorate}/edit', 'edit')->whereNumber('governorate')->name('edit')->can('Governorate Update');
            Route::put('/{governorate}', 'update')->whereNumber('governorate')->name('update')->can('Governorate Update');
            Route::delete('/{governorate}', 'destroy')->whereNumber('governorate')->name('destroy')->can('Governorate Delete');
        });

        Route::prefix('cities')->name('cities.')->controller(CityController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('City View List');
            Route::get('/add', 'add')->name('add')->can('City Create');
            Route::post('/', 'store')->name('store')->can('City Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('City Delete');
            Route::get('/{city}/edit', 'edit')->whereNumber('city')->name('edit')->can('City Update');
            Route::put('/{city}', 'update')->whereNumber('city')->name('update')->can('City Update');
            Route::delete('/{city}', 'destroy')->whereNumber('city')->name('destroy')->can('City Delete');
        });

        Route::prefix('districts')->name('districts.')->controller(DistrictController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('District View List');
            Route::get('/add', 'add')->name('add')->can('District Create');
            Route::post('/', 'store')->name('store')->can('District Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('District Delete');
            Route::get('/{district}/edit', 'edit')->whereNumber('district')->name('edit')->can('District Update');
            Route::put('/{district}', 'update')->whereNumber('district')->name('update')->can('District Update');
            Route::delete('/{district}', 'destroy')->whereNumber('district')->name('destroy')->can('District Delete');
        });
    });
});
