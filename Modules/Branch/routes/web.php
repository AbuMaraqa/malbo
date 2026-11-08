<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\Branch\Http\Controllers\Admin\BranchController;

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin/branch/appointment')->middleware('staff')->name('branches.')->controller(BranchController::class)->group(function () {
        Route::get('/', 'index')->name('index')->can('Branch View List');
        Route::get('/add', 'add')->name('add')->can('Branch Create');
        Route::post('/', 'store')->name('store')->can('Branch Create');
        Route::delete('/', 'destroyMany')->name('destroy-many')->can('Branch Delete');
        Route::get('/{branch}/edit', 'edit')->whereNumber('branch')->name('edit')->can('Branch Update');
        Route::put('/{branch}', 'update')->whereNumber('branch')->name('update')->can('Branch Update');
        Route::delete('/{branch}', 'destroy')->whereNumber('branch')->name('destroy')->can('Branch Delete');
    });
});
