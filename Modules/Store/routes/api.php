<?php

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\Api\V1\AppearanceController;
use Modules\Store\Http\Controllers\Api\V1\ContactController;
use Modules\Store\Http\Controllers\Api\V1\ContactMessageController;
use Modules\Store\Http\Controllers\Api\V1\HomeController;
use Modules\Store\Http\Controllers\Api\V1\MemberController;
use Modules\Store\Http\Controllers\Api\V1\PageController;

/*
|--------------------------------------------------------------------------
| API Routes V1
|--------------------------------------------------------------------------
|
| The storefront for the mobile app: the store's look, home texts, contact details and pages,
| and the contact and members forms. All public, on the store's own host like the website.
| Products, categories and orders are in the Items module.
|
*/

Route::prefix('v1')->as('api.v1.')->group(function () {
    Route::middleware(['api.localize'])
        ->prefix('store')
        ->as('store.')
        ->group(function () {
            Route::get('/appearance', [AppearanceController::class, 'index'])->name('appearance');
            Route::get('/home', [HomeController::class, 'index'])->name('home');
            Route::get('/contact', [ContactController::class, 'index'])->name('contact');
            Route::post('/contact-messages', [ContactMessageController::class, 'store'])->name('contact-messages.store');
            Route::post('/members', [MemberController::class, 'store'])->name('members.store');

            Route::controller(PageController::class)->prefix('pages')->as('page.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{slug}', 'show')->name('show');
            });
        });
});
