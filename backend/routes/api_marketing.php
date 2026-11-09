<?php

use App\Http\Controllers\Api\Marketing\AnalyticsController as A;
use App\Http\Controllers\Api\Marketing\CatalogController as C;
use App\Http\Controllers\Api\Marketing\ContentController as T;
use App\Http\Controllers\Api\Marketing\MediaController as MD;
use App\Http\Controllers\Api\Marketing\MerchandisingController as M;
use App\Http\Controllers\Api\Marketing\MetaController as MT;
use App\Http\Controllers\Api\Marketing\SuggestionController as S;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Support\Marketing\MarketingResponse;
use Illuminate\Support\Facades\Route;

// Marketing internal API for the MCP server (P4, MARKETER_PLAN.md): /api/internal/marketing/* (bootstrap/app.php; names marketing-api.*).
// `api` group only (no session). Auth = marketing.token:{scope} (Bearer service token, scopes, expiry/revoke, per-token throttle, request log).
// Suggestion writes need the scope of that suggestion's type, `suggestions:write:{type}` (create / edit own draft / submit) — there is deliberately NO approve, reject,
// apply or rollback route (dashboard-only). Docs: docs/marketing/API_DOCUMENTATION.md, docs/marketing/MCP_SUGGESTION_TOOLS.md.
Route::middleware('marketing.token:suggestions:write:{type}')->prefix('suggestions')->name('suggestions.')->group(function () {
    Route::post('{type}', [S::class, 'store'])->whereIn('type', SuggestionTypeKey::values())->name('store');
    Route::patch('{id}', [S::class, 'update'])->whereNumber('id')->name('update');
    Route::post('{id}/submit', [S::class, 'submit'])->whereNumber('id')->name('submit');
});

// P6 Meta (MCP.md §7): reads + drafts under meta:read, draft create / edit own / submit under meta:draft. NO approve / reject / publish /
// activate route exists: approval and "publish as PAUSED" are marketer-panel actions only (docs/marketing/META_INTEGRATION.md).
Route::middleware('marketing.token:meta:draft')->prefix('meta/drafts')->name('meta.drafts.')->group(function () {
    Route::post('/', [MT::class, 'storeDraft'])->name('store');
    Route::patch('{id}', [MT::class, 'updateDraft'])->whereNumber('id')->name('update');
    Route::post('{id}/submit', [MT::class, 'submitDraft'])->whereNumber('id')->name('submit');
});
Route::middleware('marketing.token:meta:read')->prefix('meta')->name('meta.')->group(function () {
    Route::get('status', [MT::class, 'status'])->name('status');
    Route::get('ad-accounts', [MT::class, 'adAccounts'])->name('ad-accounts');
    Route::get('account', [MT::class, 'account'])->name('account');
    Route::get('campaigns', [MT::class, 'campaigns'])->name('campaigns');
    Route::get('adsets', [MT::class, 'adSets'])->name('adsets');
    Route::get('ads', [MT::class, 'ads'])->name('ads');
    Route::get('insights', [MT::class, 'insights'])->name('insights');
    Route::get('drafts', [MT::class, 'drafts'])->name('drafts.index');
    Route::get('drafts/{id}', [MT::class, 'draft'])->whereNumber('id')->name('drafts.show');
});

Route::middleware('marketing.token:marketing:read')->group(function () {
    Route::get('overview', [A::class, 'overview'])->name('overview');
    Route::get('sales-summary', [A::class, 'salesSummary'])->name('sales-summary');
    Route::get('orders-summary', [A::class, 'ordersSummary'])->name('orders-summary');
    Route::get('best-sellers', [A::class, 'bestSellers'])->name('best-sellers');
    Route::get('slow-moving', [A::class, 'slowMoving'])->name('slow-moving');
    Route::get('low-stock', [A::class, 'lowStock'])->name('low-stock');
    Route::get('campaign-candidates', [A::class, 'campaignCandidates'])->name('campaign-candidates');
    Route::get('marketing-metrics', [A::class, 'marketingMetrics'])->name('marketing-metrics');
    Route::get('gift-finder/stats', [A::class, 'giftFinderStats'])->name('gift-finder.stats');

    Route::get('products', [C::class, 'products'])->name('products.index');
    Route::get('products/{id}', [C::class, 'product'])->whereNumber('id')->name('products.show');
    Route::get('products/{id}/profitability', [A::class, 'profitability'])->whereNumber('id')->name('products.profitability');
    Route::get('products/{id}/images', [C::class, 'images'])->whereNumber('id')->name('products.images');
    Route::get('categories', [C::class, 'categories'])->name('categories');
    Route::get('brands', [C::class, 'brands'])->name('brands');
    Route::get('inventory', [C::class, 'inventory'])->name('inventory');

    Route::get('bundles', [M::class, 'bundles'])->name('bundles');
    Route::get('banners', [M::class, 'banners'])->name('banners');
    Route::get('offers', [M::class, 'offers'])->name('offers');
    Route::get('coupons', [M::class, 'coupons'])->name('coupons');

    Route::get('theme', [T::class, 'theme'])->name('theme');
    Route::get('static-pages', [T::class, 'staticPages'])->name('static-pages');
    Route::get('seo-data', [T::class, 'seoData'])->name('seo-data');
    Route::get('blog-posts', [T::class, 'blogPosts'])->name('blog-posts');
    Route::get('blog-categories', [T::class, 'blogCategories'])->name('blog-categories');
    Route::get('seasonal-calendar', [T::class, 'seasonalCalendar'])->name('seasonal-calendar');

    // private product social-media library (P6): metadata + short-lived signed preview URLs, never file paths
    Route::get('media', [MD::class, 'index'])->name('media.index');
    Route::get('media/{id}', [MD::class, 'show'])->whereNumber('id')->name('media.show');
    Route::get('products/{id}/social-media', [MD::class, 'product'])->whereNumber('id')->name('products.social-media');

    // existing suggestions (P5)
    Route::get('suggestions', [S::class, 'index'])->name('suggestions.index');
    Route::get('suggestions/{id}', [S::class, 'show'])->whereNumber('id')->name('suggestions.show');
    Route::get('suggestions/{id}/status', [S::class, 'status'])->whereNumber('id')->name('suggestions.status');
    Route::get('suggestions/{id}/result', [S::class, 'result'])->whereNumber('id')->name('suggestions.result');

    // Unknown paths under the prefix (authenticated callers only): JSON 404 in the envelope, never the storefront.
    Route::any('{any}', fn () => MarketingResponse::error(404, 'not_found'))->where('any', '.*')->name('fallback');
});
