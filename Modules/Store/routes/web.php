<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\Admin\AppearanceController;
use Modules\Store\Http\Controllers\Admin\ContactMessageController;
use Modules\Store\Http\Controllers\Admin\MemberController;
use Modules\Store\Http\Controllers\Admin\PageController;
use Modules\Store\Http\Controllers\SitemapController;
use Modules\Store\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use Modules\Store\Http\Controllers\Storefront\ContactController as StorefrontContactController;
use Modules\Store\Http\Controllers\Storefront\JoinMembersController;
use Modules\Store\Http\Controllers\Storefront\NotFoundController;
use Modules\Store\Http\Controllers\Storefront\PageController as StorefrontPageController;
use Modules\Store\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use Modules\Store\Http\Controllers\Storefront\ShopController;

// Search engines: the store's own sitemap (robots.txt is in routes/universal.php).
Route::get('/sitemap.xml', SitemapController::class)->name('store.sitemap');

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => Locales::pattern()])->group(function () {
    Route::prefix('admin/store')->middleware('staff')->group(function () {
        Route::prefix('pages')->name('pages.')->controller(PageController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Page View List');
            Route::get('/add', 'add')->name('add')->can('Page Create');
            Route::post('/', 'store')->name('store')->can('Page Create');
            Route::get('/{page}/edit', 'edit')->whereNumber('page')->name('edit')->can('Page Update');
            Route::put('/{page}', 'update')->whereNumber('page')->name('update')->can('Page Update');
            Route::put('/{page}/published', 'published')->whereNumber('page')->name('published')->can('Page Update');
            Route::put('/{page}/footer', 'footer')->whereNumber('page')->name('footer')->can('Page Update');
            Route::delete('/{page}', 'destroy')->whereNumber('page')->name('destroy')->can('Page Delete');
        });

        Route::prefix('messages')->name('contact-messages.')->controller(ContactMessageController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Contact Message View List');
            Route::post('/read', 'readMany')->name('read-many')->can('Contact Message Update');
            Route::post('/{message}/open', 'open')->whereNumber('message')->name('open')->can('Contact Message View');
            Route::post('/{message}/unread', 'unread')->whereNumber('message')->name('unread')->can('Contact Message Update');
            Route::delete('/{message}', 'destroy')->whereNumber('message')->name('destroy')->can('Contact Message Delete');
        });

        Route::prefix('members')->name('members.')->controller(MemberController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Member View List');
            Route::delete('/{member}', 'destroy')->whereNumber('member')->name('destroy')->can('Member Delete');
        });

        Route::prefix('appearance')->name('appearance.')->controller(AppearanceController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Appearance Update');
            Route::put('/', 'update')->name('update')->can('Appearance Update');
        });
    });
});

// Storefront: public, no login (the home page is `home`, routes/universal.php).
Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->name('store.')->group(function () {
    $slug = '[\pL\pN\-_.]+';

    Route::get('/shop', ShopController::class)->name('shop');
    Route::get('/category/{slug}', StorefrontCategoryController::class)->where('slug', $slug)->name('category');
    Route::get('/product/{slug}', StorefrontProductController::class)->where('slug', $slug)->name('product');
    Route::get('/page/{slug}', StorefrontPageController::class)->where('slug', $slug)->name('page');
    Route::get('/contact', [StorefrontContactController::class, 'show'])->name('contact');
    Route::post('/contact', [StorefrontContactController::class, 'send'])->name('contact.send');
    Route::get('/join', [JoinMembersController::class, 'show'])->name('members');
    Route::post('/join', [JoinMembersController::class, 'store'])->name('members.join');

    // Any other address under a language: the 404 inside the store's layout. Stays last; modules
    // load before this one (StorefrontRoutesTest), so it never hides another module's page.
    Route::get('/{path}', NotFoundController::class)->where('path', '.*')->name('not-found');
});
