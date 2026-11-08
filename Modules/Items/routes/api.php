<?php

use Illuminate\Support\Facades\Route;
use Modules\Items\Http\Controllers\Api\V1\AddonController;
use Modules\Items\Http\Controllers\Api\V1\AttributeController;
use Modules\Items\Http\Controllers\Api\V1\CategoryController;
use Modules\Items\Http\Controllers\Api\V1\OfferController;
use Modules\Items\Http\Controllers\Api\V1\OrderController;
use Modules\Items\Http\Controllers\Api\V1\ProductController;
use Modules\Items\Http\Controllers\Api\V1\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes V1
|--------------------------------------------------------------------------
|
| API v1 routes are grouped here.
| Routes are divided into three main sections:
| 1. Authentication: For guests (login, registration) and authenticated users (logout).
| 2. Public Routes: Accessible by anyone (e.g., viewing products, categories).
| 3. Protected Routes: Require authentication (e.g., creating an order, viewing profile).
|
*/

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::middleware(['api.localize'])
        ->prefix('items')
        ->as('item.')
        ->group(function () {

            // Categories
            Route::controller(CategoryController::class)->prefix('categories')->as('category.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{category}', 'show')->whereNumber('category')->name('show');
            });

            // Products
            Route::controller(ProductController::class)->prefix('products')->as('product.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{product}', 'show')->whereNumber('product')->name('show');
                Route::get('/{product}/attributes', 'getAttributes')->whereNumber('product')->name('getAttributes');
                Route::get('/{product}/addons', 'getAddons')->whereNumber('product')->name('getAddons');
            });

            // Attributes
            Route::controller(AttributeController::class)->prefix('attributes')->as('attribute.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{attribute}', 'show')->whereNumber('attribute')->name('show');
            });

            // Offers
            Route::controller(OfferController::class)->prefix('offers')->as('offer.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{offer}', 'show')->whereNumber('offer')->name('show');
            });

            // Addons
            Route::controller(AddonController::class)->prefix('addons')->as('addon.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{addon}', 'show')->whereNumber('addon')->name('show');
            });

        });

    Route::middleware(['auth:sanctum', 'api.localize'])
        ->prefix('items')
        ->as('item.')
        ->group(function () {

            // A buyer lists, reads and places their own orders; staff see all of them with
            // `Order View List` (see OrderController).
            Route::controller(OrderController::class)->prefix('orders')->as('order.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{order}', 'show')->whereNumber('order')->name('show');
                Route::post('/', 'store')->name('store');
                Route::patch('/{order}/status', 'updateOrderStatus')->whereNumber('order')->name('updateOrderStatus')->can('Order Update');
            });

            // For the staff's apps: drafts and inactive records are included, unlike the public catalog.
            Route::controller(SyncController::class)->prefix('sync')->as('sync.')->group(function () {
                Route::get('/products', 'syncProducts')->name('products')->can('Product View List');
                Route::get('/categories', 'syncCategories')->name('categories')->can('Category View List');
                Route::get('/addons', 'syncAddons')->name('addons')->can('Addon View List');
                Route::get('/addonsOptions', 'syncAddonsOptions')->name('addonsOptions')->can('Addon View List');
                Route::get('/offers', 'syncOffers')->name('offers')->can('Offer View List');
                Route::get('/orders', 'syncOrders')->name('orders')->can('Order View List');
            });
        });
});
