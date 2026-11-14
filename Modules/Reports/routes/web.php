<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\Admin\CartReportController;
use Modules\Reports\Http\Controllers\Admin\ChannelReportController;
use Modules\Reports\Http\Controllers\Admin\ConversionReportController;
use Modules\Reports\Http\Controllers\Admin\MerchantReportController;
use Modules\Reports\Http\Controllers\Admin\ProductReportController;
use Modules\Reports\Http\Controllers\Admin\ReturnReportController;
use Modules\Reports\Http\Controllers\Admin\SalesReportController;
use Modules\Reports\Http\Controllers\Admin\SellerReportController;

// Each report: its page and its CSV export, behind the report's own permission.
Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin/reports')->middleware('staff')->name('reports.')->group(function () {
        $reports = [
            'sales' => [SalesReportController::class, 'Sales Report View'],
            'channels' => [ChannelReportController::class, 'Channel Report View'],
            'products' => [ProductReportController::class, 'Product Report View'],
            'sellers' => [SellerReportController::class, 'Seller Report View'],
            'returns' => [ReturnReportController::class, 'Return Report View'],
            'merchants' => [MerchantReportController::class, 'Merchant Report View'],
            'carts' => [CartReportController::class, 'Cart Report View'],
            'conversion' => [ConversionReportController::class, 'Conversion Report View'],
        ];

        foreach ($reports as $name => [$controller, $permission]) {
            Route::prefix($name)->name($name.'.')->controller($controller)->group(function () use ($permission) {
                Route::get('/', 'index')->name('index')->can($permission);
                Route::get('/export', 'export')->name('export')->can($permission);
            });
        }
    });
});
