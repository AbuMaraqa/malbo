<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\RobotsController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

Route::get('/', LocaleRedirectController::class);

Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    // The storefront home page.
    Route::get('/', HomeController::class)->name('home');
});
