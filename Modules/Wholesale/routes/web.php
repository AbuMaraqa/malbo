<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\Wholesale\Http\Controllers\Admin\MerchantController;
use Modules\Wholesale\Http\Controllers\Admin\MerchantLedgerController;
use Modules\Wholesale\Http\Controllers\Admin\PreorderController;
use Modules\Wholesale\Http\Controllers\Admin\ProductPriceController;
use Modules\Wholesale\Http\Controllers\Admin\TierController;
use Modules\Wholesale\Http\Controllers\Storefront\ApplyController;
use Modules\Wholesale\Http\Controllers\Storefront\CartController;
use Modules\Wholesale\Http\Controllers\Storefront\CatalogPdfController;
use Modules\Wholesale\Http\Controllers\Storefront\CatalogController;
use Modules\Wholesale\Http\Controllers\Storefront\CheckoutController;
use Modules\Wholesale\Http\Controllers\Storefront\OrderController;
use Modules\Wholesale\Http\Controllers\Storefront\PortalController;
use Modules\Wholesale\Http\Controllers\Storefront\ProductController;
use Modules\Wholesale\Http\Controllers\Storefront\StatementController;

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin/wholesale')->middleware('staff')->group(function () {
        Route::prefix('merchants')->name('merchants.')->controller(MerchantController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Merchant View List');
            Route::get('/{merchant}/edit', 'edit')->whereNumber('merchant')->name('edit')->can('Merchant View List');
            Route::get('/{merchant}/document', 'document')->whereNumber('merchant')->middleware('signed')->name('document')->can('Merchant View List');
            Route::put('/{merchant}', 'update')->whereNumber('merchant')->name('update')->can('Merchant Update');
            Route::put('/{merchant}/status', 'status')->whereNumber('merchant')->name('status')->can('Merchant Update');
        });

        // A merchant's finance: the ledger, payments, adjustments, receipts and statements.
        Route::prefix('merchants/{merchant}')->whereNumber('merchant')->name('merchants.')->controller(MerchantLedgerController::class)->group(function () {
            Route::get('/ledger', 'index')->name('ledger')->can('Merchant View List');
            Route::get('/ledger/{entry}/receipt', 'receipt')->whereNumber('entry')->middleware('signed')->name('receipt')->can('Merchant View List');
            Route::get('/statement', 'statement')->name('statement')->can('Merchant View List');
            Route::post('/payments', 'storePayment')->name('payments.store')->can('Merchant Payment Create');
            Route::post('/adjustments', 'storeAdjustment')->name('adjustments.store')->can('Merchant Payment Create');
        });

        Route::prefix('tiers')->name('wholesale-tiers.')->controller(TierController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Wholesale Tier View List');
            Route::get('/add', 'add')->name('add')->can('Wholesale Tier Create');
            Route::post('/', 'store')->name('store')->can('Wholesale Tier Create');
            Route::put('/settings', 'settings')->name('settings')->can('Wholesale Tier Update');
            Route::get('/{tier}/edit', 'edit')->whereNumber('tier')->name('edit')->can('Wholesale Tier Update');
            Route::put('/{tier}', 'update')->whereNumber('tier')->name('update')->can('Wholesale Tier Update');
            Route::delete('/{tier}', 'destroy')->whereNumber('tier')->name('destroy')->can('Wholesale Tier Delete');
        });

        Route::prefix('prices')->name('wholesale-prices.')->controller(ProductPriceController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Wholesale Price View List');
            Route::get('/{product}/edit', 'edit')->whereNumber('product')->name('edit')->can('Wholesale Price Update');
            Route::put('/{product}', 'update')->whereNumber('product')->name('update')->can('Wholesale Price Update');
            Route::put('/{product}/incoming', 'incoming')->whereNumber('product')->name('incoming')->can('Wholesale Price Update');
        });

        Route::get('/preorders', [PreorderController::class, 'index'])->name('wholesale-preorders.index')->can('Wholesale Preorder View List');
    });
});

// The portal: storefront pages (`store.*` names: the store's layout, routes and sign-in). Registered
// before Store's catch-all (module priority -1). Prices only behind `merchant.approved`.
Route::prefix('{locale}/wholesale')->where(['locale' => Locales::pattern()])->name('store.wholesale.')->group(function () {
    Route::get('/apply', [ApplyController::class, 'show'])->name('apply');
    Route::post('/apply', [ApplyController::class, 'store'])->middleware('throttle:wholesale-apply')->name('apply.store');

    // A merchant's shared PDF catalog: no login; signed, expiring and revocable (CatalogPdfController::shared).
    Route::get('/shared-catalog/{token}', [CatalogPdfController::class, 'shared'])->where('token', '[A-Za-z0-9]{32,64}')
        ->middleware(['signed', 'throttle:wholesale-catalog-shared'])->name('catalog-pdf.shared');

    Route::middleware(['auth', 'auth.session'])->group(function () {
        Route::get('/account', [PortalController::class, 'status'])->name('status');

        // The statement stays open to a suspended merchant: it holds no wholesale price.
        Route::middleware('merchant.profile')->group(function () {
            Route::get('/account/statement', [StatementController::class, 'show'])->name('statement');
            Route::get('/account/statement/pdf', [StatementController::class, 'pdf'])->middleware('throttle:wholesale-statement')->name('statement.pdf');
        });

        Route::middleware('merchant.approved')->group(function () {
            Route::get('/', CatalogController::class)->name('catalog');
            Route::get('/product/{slug}', ProductController::class)->where('slug', '[\pL\pN\-_.]+')->name('product');

            Route::get('/cart', [CartController::class, 'show'])->name('cart');
            Route::put('/cart/{product}', [CartController::class, 'set'])->whereNumber('product')->name('cart.set');
            Route::delete('/cart/{product}', [CartController::class, 'remove'])->whereNumber('product')->name('cart.remove');

            Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
            Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:wholesale-checkout')->name('checkout.place');
            Route::get('/checkout/done', [CheckoutController::class, 'done'])->name('checkout.done');

            Route::get('/orders', [OrderController::class, 'index'])->name('orders');
            Route::get('/orders/{number}', [OrderController::class, 'show'])->where('number', '[A-Za-z0-9\-]+')->name('orders.show');
            Route::post('/orders/{number}/reorder', [OrderController::class, 'reorder'])->where('number', '[A-Za-z0-9\-]+')->name('orders.reorder');

            Route::get('/catalog-pdf', [CatalogPdfController::class, 'show'])->name('catalog-pdf');
            Route::get('/catalog-pdf/download', [CatalogPdfController::class, 'download'])->middleware('throttle:wholesale-catalog')->name('catalog-pdf.download');
            Route::post('/catalog-pdf/shares', [CatalogPdfController::class, 'storeShare'])->middleware('throttle:wholesale-catalog')->name('catalog-pdf.shares.store');
            Route::delete('/catalog-pdf/shares/{share}', [CatalogPdfController::class, 'destroyShare'])->whereNumber('share')->name('catalog-pdf.shares.destroy');
        });
    });
});
