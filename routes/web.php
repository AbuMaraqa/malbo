<?php

use App\Http\Controllers\StoreImpersonationController;
use Illuminate\Support\Facades\Route;

// Sign-in links from the platform dashboard (no auth: the single-use token is the credential).
Route::get('/impersonate/{token}', StoreImpersonationController::class)->name('store.impersonate');
