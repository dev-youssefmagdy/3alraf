<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Http\Controllers\Storefront\AffiliateAuthController;
use App\Http\Controllers\Storefront\AffiliateDashboardController;
use App\Http\Controllers\Storefront\CustomPageController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ReviewController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'root'])->name('storefront.root');
Route::get('sitemap.xml', SitemapController::class)->name('storefront.sitemap');

// Payment gateway callbacks (public, signature-verified, CSRF-exempt: bootstrap/app.php)
Route::post('webhooks/payments/{method}', PaymentWebhookController::class)->middleware('throttle:webhooks')->whereNumber('method')->name('webhooks.payments');

Route::prefix('{locale}')->where(['locale' => '[a-z]{2,3}(-[A-Za-z]{2})?'])->middleware(['set.locale', 'affiliate.track'])->name('storefront.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::middleware('guest:web')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login/validate', [LoginController::class, 'validateForm'])->middleware('throttle:login-validate')->name('login.validate');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    });
    Route::middleware('guest:web')->group(function () {
        Route::get('register', [CustomerAuthController::class, 'show'])->name('register');
        Route::post('register/validate', [CustomerAuthController::class, 'validateForm'])->name('register.validate');
        Route::post('register', [CustomerAuthController::class, 'store'])->middleware('throttle:10,1')->name('register.submit');
    });
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:web')->name('logout');

    // Cart (guest session token or customer), checkout, order pages by public token
    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('cart/items', [CartController::class, 'add'])->middleware('throttle:cart')->name('cart.add');
    Route::put('cart/items/{item}', [CartController::class, 'update'])->middleware('throttle:cart')->whereNumber('item')->name('cart.update');
    Route::delete('cart/items/{item}', [CartController::class, 'remove'])->middleware('throttle:cart')->whereNumber('item')->name('cart.remove');
    Route::post('cart/quote', [CartController::class, 'quote'])->middleware('throttle:quote')->name('cart.quote');
    Route::get('checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::get('checkout/shipping', [CheckoutController::class, 'shipping'])->name('checkout.shipping');
    Route::post('checkout/validate', [CheckoutController::class, 'validateForm'])->middleware('throttle:checkout-validate')->name('checkout.validate');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');
    Route::get('order/{token}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('order/{token}/invoice.pdf', [OrderController::class, 'invoicePdf'])->middleware('throttle:upload')->name('orders.invoice.pdf');
    Route::post('order/{token}/proof', [OrderController::class, 'uploadProof'])->middleware('throttle:upload')->name('orders.proof');
    Route::get('order/{token}/proof', [OrderController::class, 'proofFile'])->middleware('throttle:upload')->name('orders.proof.file');
    Route::post('order/{token}/pay', [OrderController::class, 'pay'])->middleware('throttle:upload')->name('orders.pay');
    Route::post('order/{token}/return', [OrderController::class, 'requestReturn'])->middleware(['auth:web', 'throttle:returns'])->name('orders.return');
    Route::get('payments/return/{token}', [OrderController::class, 'paymentReturn'])->name('payments.return');

    // Phase 7: catalog
    Route::get('category/{slug}', [CatalogController::class, 'category'])->name('category.show');
    Route::get('brand/{slug}', [CatalogController::class, 'brand'])->name('brand.show');
    Route::get('tag/{slug}', [CatalogController::class, 'tag'])->name('tag.show');
    Route::get('search', [CatalogController::class, 'results'])->middleware('throttle:search')->name('search');
    Route::get('search/suggest', [CatalogController::class, 'suggest'])->middleware('throttle:search')->name('search.suggest');

    // Phase 5: product page, reviews, static pages, wishlist
    Route::get('product/{slug}', [ProductController::class, 'show'])->name('product.show');
    Route::get('page/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::middleware('auth:web')->group(function () {
        Route::post('product/{slug}/reviews/validate', [ReviewController::class, 'validateForm'])->name('reviews.validate');
        Route::post('product/{slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
        Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('wishlist/{product}/toggle', [WishlistController::class, 'toggle'])->whereNumber('product')->name('wishlist.toggle');
    });

    // Affiliates (own guard)
    Route::prefix('affiliate')->name('affiliate.')->group(function () {
        Route::middleware('guest:affiliate')->group(function () {
            Route::get('login', [AffiliateAuthController::class, 'showLogin'])->name('login');
            Route::post('login/validate', [AffiliateAuthController::class, 'validateLogin'])->middleware('throttle:login-validate')->name('login.validate');
            Route::post('login', [AffiliateAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
            Route::get('register', [AffiliateAuthController::class, 'showRegister'])->name('register');
            Route::post('register/validate', [AffiliateAuthController::class, 'validateRegister'])->name('register.validate');
            Route::post('register', [AffiliateAuthController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');
        });
        Route::middleware('auth:affiliate')->group(function () {
            Route::post('logout', [AffiliateAuthController::class, 'logout'])->name('logout');
            Route::get('/', [AffiliateDashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('products', [AffiliateDashboardController::class, 'products'])->name('products');
            Route::get('qr/{slug}', [AffiliateDashboardController::class, 'qr'])->name('qr');
            Route::post('payouts', [AffiliateDashboardController::class, 'requestPayout'])->middleware('throttle:payout')->name('payouts.request');
        });
    });

    Route::middleware('auth:web')->prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::put('profile', [AccountController::class, 'update'])->name('profile.update');
        Route::post('profile/validate', [AccountController::class, 'validateForm'])->name('profile.validate');
        Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
        Route::post('addresses/validate', [AddressController::class, 'validateForm'])->middleware('throttle:addresses')->name('addresses.validate');
        Route::post('addresses', [AddressController::class, 'store'])->middleware('throttle:addresses')->name('addresses.store');
        Route::put('addresses/{id}', [AddressController::class, 'update'])->middleware('throttle:addresses')->name('addresses.update');
        Route::delete('addresses/{id}', [AddressController::class, 'destroy'])->middleware('throttle:addresses')->name('addresses.destroy');
    });
});

// Custom pages `/{slug}` (reserved slugs: config/tenancy.php + App\Support\ReservedSlugs) must be registered AFTER everything above.
Route::get('{slug}', [CustomPageController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('storefront.custom-page');
