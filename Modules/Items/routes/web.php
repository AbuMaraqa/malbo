<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\Items\Http\Controllers\Admin\AddonController;
use Modules\Items\Http\Controllers\Admin\AttributeController;
use Modules\Items\Http\Controllers\Admin\BrandController;
use Modules\Items\Http\Controllers\Admin\BundleController;
use Modules\Items\Http\Controllers\Admin\CategoryController;
use Modules\Items\Http\Controllers\Admin\LabelController;
use Modules\Items\Http\Controllers\Admin\OfferController;
use Modules\Items\Http\Controllers\Admin\OrderController;
use Modules\Items\Http\Controllers\Admin\OrderDocumentController;
use Modules\Items\Http\Controllers\Admin\ProductController;
use Modules\Items\Http\Controllers\Admin\ReturnRequestController;
use Modules\Items\Http\Controllers\Admin\ShipmentController;
use Modules\Items\Http\Controllers\Admin\StockController;
use Modules\Items\Http\Controllers\Admin\TagController;

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin')->middleware('staff')->group(function () {
        Route::prefix('categories')->name('categories.')->controller(CategoryController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Category View List');
            Route::get('/add', 'add')->name('add')->can('Category Create');
            Route::post('/', 'store')->name('store')->can('Category Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Category Delete');
            Route::get('/{category}/info', 'info')->whereNumber('category')->name('info')->can('Category Info');
            Route::get('/{category}/edit', 'edit')->whereNumber('category')->name('edit')->can('Category Update');
            Route::put('/{category}/status', 'status')->whereNumber('category')->name('status')->can('Category Update');
            Route::put('/{category}', 'update')->whereNumber('category')->name('update')->can('Category Update');
            Route::delete('/{category}', 'destroy')->whereNumber('category')->name('destroy')->can('Category Delete');
        });

        Route::prefix('tags')->name('tags.')->controller(TagController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Tag View List');
            Route::post('/', 'store')->name('store')->can('Tag Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Tag Delete');
            Route::get('/{tag}/info', 'info')->whereNumber('tag')->name('info')->can('Tag Info');
            Route::put('/{tag}', 'update')->whereNumber('tag')->name('update')->can('Tag Update');
            Route::delete('/{tag}', 'destroy')->whereNumber('tag')->name('destroy')->can('Tag Delete');
        });

        Route::prefix('labels')->name('labels.')->controller(LabelController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Label View List');
            Route::post('/', 'store')->name('store')->can('Label Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Label Delete');
            Route::get('/{label}/info', 'info')->whereNumber('label')->name('info')->can('Label Info');
            Route::put('/{label}', 'update')->whereNumber('label')->name('update')->can('Label Update');
            Route::delete('/{label}', 'destroy')->whereNumber('label')->name('destroy')->can('Label Delete');
        });

        // The URL is /brand, as in core; the names are brands.*.
        Route::prefix('brand')->name('brands.')->controller(BrandController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Brand View List');
            Route::post('/', 'store')->name('store')->can('Brand Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Brand Delete');
            Route::get('/{brand}/info', 'info')->whereNumber('brand')->name('info')->can('Brand Info');
            Route::put('/{brand}', 'update')->whereNumber('brand')->name('update')->can('Brand Update');
            Route::delete('/{brand}', 'destroy')->whereNumber('brand')->name('destroy')->can('Brand Delete');
        });

        Route::prefix('attributes')->name('attributes.')->controller(AttributeController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Attribute View List');
            Route::get('/add', 'add')->name('add')->can('Attribute Create');
            Route::post('/', 'store')->name('store')->can('Attribute Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Attribute Delete');
            Route::get('/{attribute}/info', 'info')->whereNumber('attribute')->name('info')->can('Attribute Info');
            Route::get('/{attribute}/edit', 'edit')->whereNumber('attribute')->name('edit')->can('Attribute Update');
            Route::put('/{attribute}/values/order', 'reorderValues')->whereNumber('attribute')->name('values.reorder')->can('Attribute Update');
            Route::put('/{attribute}', 'update')->whereNumber('attribute')->name('update')->can('Attribute Update');
            Route::delete('/{attribute}', 'destroy')->whereNumber('attribute')->name('destroy')->can('Attribute Delete');
        });

        Route::prefix('addons')->name('addons.')->controller(AddonController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Addon View List');
            Route::get('/add', 'add')->name('add')->can('Addon Create');
            Route::post('/', 'store')->name('store')->can('Addon Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Addon Delete');
            Route::get('/{addon}/info', 'info')->whereNumber('addon')->name('info')->can('Addon Info');
            Route::get('/{addon}/edit', 'edit')->whereNumber('addon')->name('edit')->can('Addon Update');
            Route::put('/{addon}', 'update')->whereNumber('addon')->name('update')->can('Addon Update');
            Route::delete('/{addon}', 'destroy')->whereNumber('addon')->name('destroy')->can('Addon Delete');
        });

        Route::prefix('products')->name('products.')->controller(ProductController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Product View List');
            Route::get('/add', 'add')->name('add')->can('Product Create');
            Route::post('/', 'store')->name('store')->can('Product Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Product Delete');
            // "Complete the look" (phase 8B): the products shown next to a product.
            Route::get('/look-lookup', 'lookLookup')->name('look.lookup')->can('Product Update');
            Route::put('/{product}/look', 'look')->whereNumber('product')->name('look.update')->can('Product Update');
            Route::get('/{product}/info', 'info')->whereNumber('product')->name('info')->can('Product Info');
            Route::get('/{product}/edit', 'edit')->whereNumber('product')->name('edit')->can('Product Update');
            Route::get('/{product}/barcodes', 'barcodes')->whereNumber('product')->name('barcodes')->can('Product View List');
            Route::get('/{product}/barcodes/pdf', 'barcodesPdf')->whereNumber('product')->name('barcodes.pdf')->can('Product View List');
            Route::get('/{product}/branch-pricings', 'branchPricings')->whereNumber('product')->name('branch-pricings')->can('Product Branch Pricings');
            Route::put('/{product}/branch-pricings', 'saveBranchPricings')->whereNumber('product')->name('branch-pricings.update')->can('Product Branch Pricings');
            Route::put('/{product}/status', 'status')->whereNumber('product')->name('status')->can('Product Update');
            Route::put('/{product}/active', 'active')->whereNumber('product')->name('active')->can('Product Update');
            Route::put('/{product}', 'update')->whereNumber('product')->name('update')->can('Product Update');
            Route::delete('/{product}', 'destroy')->whereNumber('product')->name('destroy')->can('Product Delete');
        });


        // Sets and bundle offers (phase 8B): products made of components, sold on the storefront only.
        Route::prefix('bundles')->name('bundles.')->controller(BundleController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Bundle View List');
            Route::get('/add', 'add')->name('add')->can('Bundle Create');
            Route::post('/', 'store')->name('store')->can('Bundle Create');
            Route::get('/lookup', 'lookup')->name('lookup')->can('Bundle View List');
            Route::get('/{bundle}/edit', 'edit')->whereNumber('bundle')->name('edit')->can('Bundle Update');
            Route::put('/{bundle}', 'update')->whereNumber('bundle')->name('update')->can('Bundle Update');
            Route::delete('/{bundle}', 'destroy')->whereNumber('bundle')->name('destroy')->can('Bundle Delete');
        });

        Route::prefix('offers')->name('offers.')->controller(OfferController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Offer View List');
            Route::get('/add', 'add')->name('add')->can('Offer Create');
            Route::post('/', 'store')->name('store')->can('Offer Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Offer Delete');
            Route::get('/{offer}/info', 'info')->whereNumber('offer')->name('info')->can('Offer Info');
            Route::get('/{offer}/edit', 'edit')->whereNumber('offer')->name('edit')->can('Offer Update');
            Route::put('/{offer}', 'update')->whereNumber('offer')->name('update')->can('Offer Update');
            Route::delete('/{offer}', 'destroy')->whereNumber('offer')->name('destroy')->can('Offer Delete');
        });

        Route::prefix('orders')->name('orders.')->controller(OrderController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Order View List');
            Route::get('/add', 'add')->name('add')->can('Order Create');
            Route::post('/', 'store')->name('store')->can('Order Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Order Delete');
            // Same search, one route per permission: who may open Add, or Edit, may search from it.
            Route::get('/lookup/{type}', 'lookup')->whereIn('type', ['customers', 'products'])->name('lookup')->can('Order Create');
            Route::get('/edit-lookup/{type}', 'lookup')->whereIn('type', ['customers', 'products'])->name('edit-lookup')->can('Order Update');
            Route::get('/{order}/info', 'info')->whereNumber('order')->name('info')->can('Order Info');
            Route::get('/{order}/edit', 'edit')->whereNumber('order')->name('edit')->can('Order Update');
            Route::put('/{order}/confirm', 'confirm')->whereNumber('order')->name('confirm')->can('Order Update');
            Route::put('/{order}', 'update')->whereNumber('order')->name('update')->can('Order Update');
            Route::delete('/{order}', 'destroy')->whereNumber('order')->name('destroy')->can('Order Delete');
        });

        // An order's papers (PDF): the invoice and the courier's waybill, one or many at once.
        Route::prefix('orders')->name('orders.')->controller(OrderDocumentController::class)->group(function () {
            Route::get('/waybills', 'waybills')->name('waybills')->can('Order Invoice');
            Route::get('/{order}/invoice', 'invoice')->whereNumber('order')->name('invoice')->can('Order Invoice');
            Route::get('/{order}/waybill', 'waybill')->whereNumber('order')->name('waybill')->can('Order Invoice');
        });

        Route::prefix('shipments')->name('shipments.')->controller(ShipmentController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Shipment View List');
            Route::post('/', 'store')->name('store')->can('Shipment Create');
            Route::put('/{shipment}/status', 'status')->whereNumber('shipment')->name('status')->can('Shipment Update');
        });

        Route::prefix('stock')->name('stock.')->controller(StockController::class)->group(function () {
            Route::get('/movements', 'movements')->name('movements')->can('Stock View List');
            Route::get('/low', 'lowStock')->name('low')->can('Stock View List');
            Route::get('/receive', 'receiveForm')->name('receive')->can('Stock Update');
            Route::post('/receive', 'receive')->name('receive.store')->can('Stock Update');
            Route::get('/adjust', 'adjustForm')->name('adjust')->can('Stock Update');
            Route::post('/adjust', 'adjust')->name('adjust.store')->can('Stock Update');
            Route::get('/products', 'products')->name('products')->can('Stock Update');
        });

        Route::prefix('return-requests')->name('return-requests.')->controller(ReturnRequestController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Return Request View List');
            Route::get('/add', 'add')->name('add')->can('Return Request Create');
            Route::post('/', 'store')->name('store')->can('Return Request Create');
            Route::get('/products', 'products')->name('products')->can('Return Request Create');
            Route::put('/settings', 'settings')->name('settings')->can('Return Request Update');
            Route::get('/{returnRequest}/edit', 'edit')->whereNumber('returnRequest')->name('edit')->can('Return Request Update');
            Route::put('/{returnRequest}/status', 'status')->whereNumber('returnRequest')->name('status')->can('Return Request Update');
            Route::put('/{returnRequest}', 'update')->whereNumber('returnRequest')->name('update')->can('Return Request Update');
        });
    });
});
