<?php

use App\Support\Locales;
use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\Admin\AppearanceController;
use Modules\Store\Http\Controllers\Admin\AbandonedCartController;
use Modules\Store\Http\Controllers\Admin\CampaignPageController;
use Modules\Store\Http\Controllers\Admin\InstagramPostController;
use Modules\Store\Http\Controllers\Storefront\CampaignController;
use Modules\Store\Http\Controllers\Admin\MarketingSettingsController;
use Modules\Store\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Modules\Store\Http\Controllers\Admin\StockAlertController as AdminStockAlertController;
use Modules\Store\Http\Controllers\CatalogFeedController;
use Modules\Store\Http\Controllers\Storefront\CartContactController;
use Modules\Store\Http\Controllers\Storefront\CartRestoreController;
use Modules\Store\Http\Controllers\Storefront\ReviewController;
use Modules\Store\Http\Controllers\Storefront\StockAlertController;
use Modules\Store\Http\Controllers\Storefront\UnsubscribeController;
use Modules\Store\Http\Controllers\Admin\ContactMessageController;
use Modules\Store\Http\Controllers\Admin\MemberController;
use Modules\Store\Http\Controllers\Admin\PageController;
use Modules\Store\Http\Controllers\Admin\ShippingZoneController;
use Modules\Store\Http\Controllers\SitemapController;
use Modules\Store\Http\Controllers\Storefront\AccountAuthController;
use Modules\Store\Http\Controllers\Storefront\AccountController;
use Modules\Store\Http\Controllers\Storefront\AccountOrderController;
use Modules\Store\Http\Controllers\Storefront\AccountReturnController;
use Modules\Store\Http\Controllers\Storefront\AddressController;
use Modules\Store\Http\Controllers\Storefront\CartController;
use Modules\Store\Http\Controllers\Storefront\PasswordResetController;
use Modules\Store\Http\Controllers\Storefront\WishlistController;
use Modules\Store\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use Modules\Store\Http\Controllers\Storefront\CheckoutController;
use Modules\Store\Http\Controllers\Storefront\ConsentController;
use Modules\Store\Http\Controllers\Storefront\TrackOrderController;
use Modules\Store\Http\Controllers\Storefront\CategoriesController as StorefrontCategoriesController;
use Modules\Store\Http\Controllers\Storefront\ContactController as StorefrontContactController;
use Modules\Store\Http\Controllers\Storefront\JoinMembersController;
use Modules\Store\Http\Controllers\Storefront\NotFoundController;
use Modules\Store\Http\Controllers\Storefront\PageController as StorefrontPageController;
use Modules\Store\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use Modules\Store\Http\Controllers\Storefront\SearchController;
use Modules\Store\Http\Controllers\Storefront\ShopController;

// Search engines: the store's own sitemap (robots.txt is in routes/universal.php).
Route::get('/sitemap.xml', SitemapController::class)->name('store.sitemap');

// Facebook and Instagram's product catalog (Commerce Manager reads it). `?token=` when the store set one.
Route::get('/feeds/meta-catalog.xml', [CatalogFeedController::class, 'xml'])->middleware('throttle:60,1')->name('store.feeds.meta-catalog');
Route::get('/feeds/meta-catalog.csv', [CatalogFeedController::class, 'csv'])->middleware('throttle:60,1')->name('store.feeds.meta-catalog-csv');

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

        // Marketing (phase 8A).
        Route::prefix('marketing')->name('marketing.')->controller(MarketingSettingsController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Marketing Settings Update');
            Route::put('/', 'update')->name('update')->can('Marketing Settings Update');
        });

        Route::prefix('abandoned-carts')->name('abandoned-carts.')->controller(AbandonedCartController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Abandoned Cart View List');
            Route::get('/{cart}', 'show')->whereNumber('cart')->name('show')->can('Abandoned Cart View List');
        });

        Route::get('/stock-alerts', [AdminStockAlertController::class, 'index'])->name('stock-alerts.index')->can('Stock Alert View List');

        // Campaign pages (phase 8B): built from blocks, each with its slug and publish window.
        Route::prefix('campaigns')->name('campaign-pages.')->controller(CampaignPageController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Campaign Page View List');
            Route::get('/add', 'add')->name('add')->can('Campaign Page Create');
            Route::post('/', 'store')->name('store')->can('Campaign Page Create');
            Route::get('/products', 'products')->name('products')->can('Campaign Page View List');
            Route::get('/{page}/edit', 'edit')->whereNumber('page')->name('edit')->can('Campaign Page Update');
            Route::put('/{page}', 'update')->whereNumber('page')->name('update')->can('Campaign Page Update');
            Route::delete('/{page}', 'destroy')->whereNumber('page')->name('destroy')->can('Campaign Page Delete');
        });

        // Instagram content (phase 8B): posts entered here, the home section's switch.
        Route::prefix('instagram')->name('instagram-posts.')->controller(InstagramPostController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Instagram Post View List');
            Route::get('/add', 'add')->name('add')->can('Instagram Post Create');
            Route::post('/', 'store')->name('store')->can('Instagram Post Create');
            Route::put('/settings', 'settings')->name('settings')->can('Instagram Post Update');
            Route::get('/products', 'products')->name('products')->can('Instagram Post View List');
            Route::get('/{post}/edit', 'edit')->whereNumber('post')->name('edit')->can('Instagram Post Update');
            Route::put('/{post}/status', 'status')->whereNumber('post')->name('status')->can('Instagram Post Update');
            Route::put('/{post}', 'update')->whereNumber('post')->name('update')->can('Instagram Post Update');
            Route::delete('/{post}', 'destroy')->whereNumber('post')->name('destroy')->can('Instagram Post Delete');
        });

        Route::prefix('reviews')->name('reviews.')->controller(AdminReviewController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Review View List');
            Route::get('/{review}/edit', 'edit')->whereNumber('review')->name('edit')->can('Review Update');
            Route::put('/{review}/status', 'status')->whereNumber('review')->name('status')->can('Review Update');
            Route::put('/{review}/reply', 'reply')->whereNumber('review')->name('reply')->can('Review Update');
            Route::delete('/{review}/photos/{media}', 'destroyPhoto')->whereNumber(['review', 'media'])->name('photos.destroy')->can('Review Update');
        });
    });

    Route::prefix('admin')->middleware('staff')->group(function () {
        Route::prefix('shipping-zones')->name('shipping-zones.')->controller(ShippingZoneController::class)->group(function () {
            Route::get('/', 'index')->name('index')->can('Shipping Zone View List');
            Route::get('/add', 'add')->name('add')->can('Shipping Zone Create');
            Route::post('/', 'store')->name('store')->can('Shipping Zone Create');
            Route::delete('/', 'destroyMany')->name('destroy-many')->can('Shipping Zone Delete');
            Route::put('/settings', 'settings')->name('settings')->can('Shipping Zone Update');
            Route::get('/{zone}/edit', 'edit')->whereNumber('zone')->name('edit')->can('Shipping Zone Update');
            Route::put('/{zone}/status', 'status')->whereNumber('zone')->name('status')->can('Shipping Zone Update');
            Route::put('/{zone}', 'update')->whereNumber('zone')->name('update')->can('Shipping Zone Update');
            Route::delete('/{zone}', 'destroy')->whereNumber('zone')->name('destroy')->can('Shipping Zone Delete');
        });
    });
});

// Storefront: public, no login (the home page is `home`, routes/universal.php).
Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->name('store.')->group(function () {
    $slug = '[\pL\pN\-_.]+';

    Route::get('/shop', ShopController::class)->name('shop');
    // The header's suggestions while typing (JSON).
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:store-search')->name('search.suggest');
    Route::get('/category/{slug}', StorefrontCategoryController::class)->where('slug', $slug)->name('category');
    Route::get('/categories', StorefrontCategoriesController::class)->name('categories');
    // Collections became categories: their old links redirect.
    Route::get('/collections', [StorefrontCategoriesController::class, 'fromCollections']);
    Route::get('/collections/{slug}', [StorefrontCategoriesController::class, 'fromCollection'])->where('slug', $slug);
    Route::get('/product/{slug}', StorefrontProductController::class)->where('slug', $slug)->name('product');
    Route::get('/page/{slug}', StorefrontPageController::class)->where('slug', $slug)->name('page');
    // Campaign pages: published, inside their dates; staff see drafts as a preview.
    Route::get('/campaign/{slug}', CampaignController::class)->where('slug', $slug)->name('campaign');
    Route::get('/contact', [StorefrontContactController::class, 'show'])->name('contact');
    Route::post('/contact', [StorefrontContactController::class, 'send'])->name('contact.send');
    Route::get('/join', [JoinMembersController::class, 'show'])->name('members');
    Route::post('/join', [JoinMembersController::class, 'store'])->name('members.join');

    // The marketing cookies banner's answer (Meta Pixel and server events).
    Route::post('/consent', ConsentController::class)->name('consent');

    // The cart (a cookie holds it), checkout as a guest, and tracking without an account.
    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
    Route::put('/cart/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.update');
    Route::delete('/cart/{item}', [CartController::class, 'remove'])->whereNumber('item')->name('cart.remove');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:store-checkout')->name('checkout.place');
    Route::get('/checkout/done', [CheckoutController::class, 'done'])->name('checkout.done');
    // Abandoned carts: the contact a buyer agreed to be reminded at, the reminder's link back to the
    // cart, and the way out of marketing messages.
    Route::post('/cart/contact', CartContactController::class)->middleware('throttle:store-cart-contact')->name('cart.contact');
    Route::get('/cart/restore/{cart}', CartRestoreController::class)->whereUuid('cart')->middleware('signed:relative')->name('cart.restore');
    Route::get('/unsubscribe', [UnsubscribeController::class, 'show'])->middleware('signed:relative')->name('unsubscribe');
    Route::post('/unsubscribe', [UnsubscribeController::class, 'confirm'])->middleware('signed:relative')->name('unsubscribe.confirm');
    // "Notify me when available" on the product page.
    Route::post('/stock-alerts', [StockAlertController::class, 'store'])->middleware('throttle:store-stock-alert')->name('stock-alerts.store');
    Route::get('/track', [TrackOrderController::class, 'show'])->name('track');
    Route::post('/track', [TrackOrderController::class, 'find'])->name('track.find');

    // Buyers' accounts. Fortify's /login stays the staff's door.
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/login', [AccountAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AccountAuthController::class, 'login'])->name('login.store');
        Route::get('/register', [AccountAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AccountAuthController::class, 'register'])->middleware('throttle:store-account')->name('register.store');
        Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:store-account')->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:store-account')->name('password.update');
    });

    Route::middleware(['auth', 'auth.session'])->group(function () use ($slug) {
        Route::get('/account', [AccountController::class, 'show'])->name('account');

        Route::prefix('account')->name('account.')->group(function () {
            Route::post('/logout', [AccountAuthController::class, 'logout'])->name('logout');
            Route::put('/profile', [AccountController::class, 'update'])->name('profile.update');
            Route::put('/password', [AccountController::class, 'password'])->name('password.change');

            Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
            Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
            Route::put('/addresses/{address}/default', [AddressController::class, 'makeDefault'])->whereNumber('address')->name('addresses.default');
            Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

            Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders');
            Route::post('/orders/claim', [AccountOrderController::class, 'claim'])->name('orders.claim');
            Route::post('/orders/{number}/returns/{returnRequest}/cancel', [AccountReturnController::class, 'cancel'])
                ->where('number', '[A-Za-z0-9\-]+')->whereNumber('returnRequest')->name('orders.returns.cancel');
            Route::get('/orders/{number}', [AccountOrderController::class, 'show'])->where('number', '[A-Za-z0-9\-]+')->name('orders.show');
            Route::post('/orders/{number}/returns', [AccountReturnController::class, 'store'])->where('number', '[A-Za-z0-9\-]+')->middleware('throttle:store-account')->name('orders.returns.store');

            Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
            Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
        });

        Route::post('/product/{slug}/reviews', [ReviewController::class, 'store'])->where('slug', $slug)->middleware('throttle:store-review')->name('reviews.store');

        Route::post('/wishlist', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    });

    // Any other address under a language: the 404 inside the store's layout. Stays last; modules
    // load before this one (StorefrontRoutesTest), so it never hides another module's page.
    Route::get('/{path}', NotFoundController::class)->where('path', '.*')->name('not-found');
});
