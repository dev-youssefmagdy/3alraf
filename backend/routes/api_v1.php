<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AffiliateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\StoreController;
use Illuminate\Support\Facades\Route;

/*
| Mobile JSON API (tenant scoped). Loaded twice by bootstrap/app.php:
|   {tenant}.domain/api/v1/...        names api.v1.*     (tenant from the host)
|   domain/api/v1/...  + X-Tenant     names api.v1.h.*   (tenant from the header)
| `api.context` = tenant + Accept-Language + X-Cart-Token + X-Affiliate-Ref. Token types: customer (ability `customer`), affiliate (ability `affiliate`).
*/
Route::middleware('throttle:api')->group(function () {
    // ---- public
    Route::get('store', [StoreController::class, 'store'])->name('store');
    Route::get('pages', [StoreController::class, 'pages'])->name('pages');
    Route::get('pages/{slug}', [StoreController::class, 'staticPage'])->name('pages.show');
    Route::get('custom-pages', [StoreController::class, 'customPages'])->name('custom-pages.index');
    Route::get('custom-pages/{slug}', [StoreController::class, 'customPage'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('custom-pages.show');

    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-strict')->name('auth.forgot');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth')->name('auth.reset');

    // ---- storefront data + cart + checkout: guests or customers (optional bearer)
    Route::middleware('api.auth:customer,optional')->group(function () {
        Route::get('home', [StoreController::class, 'home'])->name('home');
        Route::get('menu', [StoreController::class, 'menu'])->name('menu');
        Route::get('catalog', [StoreController::class, 'catalog'])->middleware('throttle:search')->name('catalog');
        Route::get('catalog/facets', [StoreController::class, 'facets'])->middleware('throttle:search')->name('catalog.facets');
        Route::get('catalog/suggest', [StoreController::class, 'suggest'])->middleware('throttle:search')->name('catalog.suggest');
        Route::get('products/{slug}', [StoreController::class, 'product'])->name('products.show');
        Route::get('products/{slug}/reviews', [AccountController::class, 'reviews'])->name('products.reviews');
        Route::get('brands', [StoreController::class, 'brands'])->name('brands');

        Route::get('cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('cart/items', [CartController::class, 'add'])->middleware('throttle:cart')->name('cart.add');
        Route::put('cart/items/{item}', [CartController::class, 'update'])->middleware('throttle:cart')->whereNumber('item')->name('cart.update');
        Route::delete('cart/items/{item}', [CartController::class, 'remove'])->middleware('throttle:cart')->whereNumber('item')->name('cart.remove');
        Route::post('cart/quote', [CartController::class, 'quote'])->middleware('throttle:quote')->name('cart.quote');

        Route::get('checkout/cities', [CheckoutController::class, 'cities'])->name('checkout.cities');
        Route::get('checkout/shipping', [CheckoutController::class, 'shipping'])->name('checkout.shipping');
        Route::get('checkout/payment-methods', [CheckoutController::class, 'paymentMethods'])->name('checkout.payment-methods');
        Route::post('checkout/validate', [CheckoutController::class, 'validateCheckout'])->middleware('throttle:checkout-validate')->name('checkout.validate');
        Route::post('checkout', [CheckoutController::class, 'place'])->middleware('throttle:checkout')->name('checkout.place');

        // order pages by public token (guest-safe)
        Route::get('orders/{token}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{token}/track', [OrderController::class, 'track'])->name('orders.track');
        Route::post('orders/{token}/proof', [OrderController::class, 'proof'])->middleware('throttle:upload')->name('orders.proof');
        Route::post('orders/{token}/pay', [OrderController::class, 'pay'])->middleware('throttle:upload')->name('orders.pay');
        Route::get('payments/return/{token}', [OrderController::class, 'paymentReturn'])->name('payments.return');
    });

    // ---- customer token
    Route::middleware('api.auth:customer')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('auth/me', [AuthController::class, 'update'])->name('auth.update');
        Route::post('auth/email/verification-notification', [AuthController::class, 'sendVerification'])->middleware('throttle:auth-strict')->name('auth.verification.send');
        Route::post('auth/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:auth')->name('auth.verification.verify');

        Route::post('cart/merge', [CartController::class, 'merge'])->middleware('throttle:cart')->name('cart.merge');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{token}/return', [OrderController::class, 'requestReturn'])->middleware('throttle:returns')->name('orders.return');

        Route::get('addresses', [AccountController::class, 'addresses'])->name('addresses.index');
        Route::post('addresses', [AccountController::class, 'storeAddress'])->middleware('throttle:addresses')->name('addresses.store');
        Route::put('addresses/{id}', [AccountController::class, 'updateAddress'])->middleware('throttle:addresses')->whereNumber('id')->name('addresses.update');
        Route::delete('addresses/{id}', [AccountController::class, 'destroyAddress'])->middleware('throttle:addresses')->whereNumber('id')->name('addresses.destroy');

        Route::get('wishlist', [AccountController::class, 'wishlist'])->name('wishlist.index');
        Route::post('wishlist/{product}/toggle', [AccountController::class, 'toggleWishlist'])->whereNumber('product')->name('wishlist.toggle');
        Route::post('products/{slug}/reviews', [AccountController::class, 'storeReview'])->middleware('throttle:reviews')->name('products.reviews.store');
    });

    // ---- affiliates (separate token type)
    Route::post('affiliate/register', [AffiliateController::class, 'register'])->middleware('throttle:auth')->name('affiliate.register');
    Route::post('affiliate/login', [AffiliateController::class, 'login'])->middleware('throttle:auth')->name('affiliate.login');
    Route::middleware('api.auth:affiliate')->prefix('affiliate')->name('affiliate.')->group(function () {
        Route::post('logout', [AffiliateController::class, 'logout'])->name('logout');
        Route::get('me', [AffiliateController::class, 'me'])->name('me');
        Route::get('dashboard', [AffiliateController::class, 'dashboard'])->name('dashboard');
        Route::get('products', [AffiliateController::class, 'products'])->name('products');
        Route::get('products/{slug}/qr', [AffiliateController::class, 'qr'])->name('qr');
        Route::get('payouts', [AffiliateController::class, 'payouts'])->name('payouts');
        Route::post('payouts', [AffiliateController::class, 'requestPayout'])->middleware('throttle:payout')->name('payouts.request');
    });
});
