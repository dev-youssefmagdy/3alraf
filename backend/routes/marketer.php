<?php

use App\Http\Controllers\Auth\MarketerLoginController;
use App\Http\Controllers\Marketer\AnalyticsController;
use App\Http\Controllers\Marketer\ApiLogController;
use App\Http\Controllers\Marketer\DashboardController;
use App\Http\Controllers\Marketer\GiftFinderController;
use App\Http\Controllers\Marketer\IntegrationClientController;
use App\Http\Controllers\Marketer\MarketerAccountController;
use App\Http\Controllers\Marketer\MarketingTokenController;
use App\Http\Controllers\Marketer\MediaLibraryController;
use App\Http\Controllers\Marketer\MetaAdsController;
use App\Http\Controllers\Marketer\SuggestionController;
use App\Http\Controllers\Marketer\Tools;
use App\Enums\Marketing\SuggestionTypeKey;
use Illuminate\Support\Facades\Route;

// Marketer panel /marketer: standalone app with its own accounts (table `marketers`, guard `marketer`), login, locale and roles.
// No route here links to /admin and no admin session opens it (ADMIN_REDESIGN_MARKETER_SPLIT_PLAN.md B1–B3). Names marketer.*.
Route::prefix('marketer')->middleware('admin.locale:marketer')->name('marketer.')->group(function () {
    Route::middleware('guest:marketer')->controller(MarketerLoginController::class)->group(function () {
        Route::get('login', 'show')->name('login');
        Route::post('login/validate', 'validateForm')->middleware('throttle:login-validate')->name('login.validate');
        Route::post('login', 'login')->middleware('throttle:auth')->name('login.submit');
        Route::get('login/otp', 'otpForm')->name('login.otp');
        Route::post('login/otp', 'verifyOtp')->middleware('throttle:auth')->name('login.otp.verify');
        Route::post('login/otp/resend', 'resendOtp')->middleware('throttle:auth-strict')->name('login.otp.resend');
    });
    Route::middleware('auth:marketer')->group(function () {
        Route::post('logout', [MarketerLoginController::class, 'logout'])->name('logout');
        Route::get('locale/{code}', function (string $code) {
            session()->put('marketer_locale', $code);

            return back();
        })->name('locale');
    });
});

// Panel gate `marketing.panel` (any marketing permission on guard `marketer`) + one `can:` per route. Nav: App\Support\Marketing\MarketerNav.
Route::prefix('marketer')->middleware(['admin.locale:marketer', 'auth:marketer', 'can:marketing.panel'])->name('marketer.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('analytics', [AnalyticsController::class, 'index'])->middleware('can:marketing.analytics')->name('analytics.index');

    // Suggestions Center (P5, MCP.md §16/§17/§24). View: ai_suggestions.view; each action its own permission (+ the type's own approve permission
    // ai_seo / ai_blog / ai_theme .approve, checked by SuggestionEngine). Approve / apply exist ONLY here (never on the API).
    Route::prefix('suggestions')->name('suggestions.')->controller(SuggestionController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:ai_suggestions.view')->name('index');
        Route::get('datatable', 'datatable')->middleware('can:ai_suggestions.view')->name('datatable');
        Route::get('log', 'log')->middleware('can:ai_suggestions.view')->name('log');
        Route::get('log/executions', 'logExecutions')->middleware('can:ai_suggestions.view')->name('log.executions');
        Route::get('log/approvals', 'logApprovals')->middleware('can:ai_suggestions.view')->name('log.approvals');
        foreach (SuggestionTypeKey::pages() as $page) {
            Route::get($page, 'page')->defaults('page', $page)->middleware('can:ai_suggestions.view')->name($page);
        }
        // images downloaded for a new_product suggestion (private disk, by content hash): reviewers only, never public
        Route::get('image/{sha}', 'image')->where('sha', '[a-f0-9]{64}')->middleware('can:ai_suggestions.view')->name('image');
        Route::post('themes/revert', 'themeRevert')->middleware('can:ai_theme.approve')->name('themes.revert');
        Route::post('themes/off', 'themeOff')->middleware('can:ai_theme.approve')->name('themes.off');
        Route::post('themes/{id}/activate', 'themeActivate')->whereNumber('id')->middleware('can:ai_theme.approve')->name('themes.activate');

        Route::prefix('{id}')->whereNumber('id')->group(function () {
            Route::get('/', 'show')->middleware('can:ai_suggestions.view')->name('show');
            Route::get('edit', 'edit')->middleware('can:ai_suggestions.edit')->name('edit');
            Route::post('validate', 'validateEdit')->middleware('can:ai_suggestions.edit')->name('validate');
            Route::put('/', 'update')->middleware('can:ai_suggestions.edit')->name('update');
            Route::post('review', 'review')->middleware('can:ai_suggestions.edit')->name('review');
            Route::post('resnapshot', 'resnapshot')->middleware('can:ai_suggestions.edit')->name('resnapshot');
            Route::post('approve', 'approve')->middleware('can:ai_suggestions.approve')->name('approve');
            Route::post('reject', 'reject')->middleware('can:ai_suggestions.reject')->name('reject');
            Route::post('apply', 'apply')->middleware('can:ai_suggestions.apply')->name('apply');
            Route::post('rollback', 'rollback')->middleware('can:ai_suggestions.apply')->name('rollback');
            Route::post('archive', 'archive')->middleware('can:ai_suggestions.archive')->name('archive');
            Route::post('publish', 'publish')->middleware(['can:ai_suggestions.apply', 'can:ai_blog.approve'])->name('publish');
            Route::get('preview', 'preview')->middleware('can:ai_suggestions.view')->name('preview');
        });
    });

    // Private product social-media library (P6, MCP.md §21): files on the private disk, streamed only by these routes (never a public URL).
    Route::prefix('media')->name('media.')->controller(MediaLibraryController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:ai_media.view')->name('index');
        Route::post('/', 'store')->middleware(['can:ai_media.upload', 'throttle:60,1'])->name('store');
        Route::prefix('{id}')->whereNumber('id')->group(function () {
            Route::get('/', 'show')->middleware('can:ai_media.view')->name('show');
            Route::get('file', 'file')->middleware('can:ai_media.view')->name('file');
            Route::get('thumb', 'thumb')->middleware('can:ai_media.view')->name('thumb');
            Route::get('download', 'download')->middleware('can:ai_media.view')->name('download');
            Route::put('/', 'update')->middleware('can:ai_media.edit')->name('update');
            Route::post('version', 'version')->middleware(['can:ai_media.upload', 'throttle:60,1'])->name('version');
            Route::post('submit', 'submit')->middleware('can:ai_media.edit')->name('submit');
            Route::post('approve', 'approve')->middleware('can:ai_media.approve')->name('approve');
            Route::post('reject', 'reject')->middleware('can:ai_media.approve')->name('reject');
            Route::post('archive', 'archive')->middleware('can:ai_media.edit')->name('archive');
            Route::delete('/', 'destroy')->middleware('can:ai_media.delete')->name('destroy');
        });
    });

    // Meta campaign drafts → approval (hash-bound) → publish as PAUSED (P6, MCP.md §7/§8). Publishing = meta_ads.publish (owner only by default).
    Route::prefix('meta-ads')->name('meta-ads.')->controller(MetaAdsController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:meta_ads.view')->name('index');
        Route::get('insights', 'insights')->middleware('can:meta_ads.view')->name('insights');
        Route::get('create', 'create')->middleware('can:meta_ads.draft')->name('create');
        Route::post('validate', 'validateForm')->middleware('can:meta_ads.draft')->name('validate');
        Route::post('/', 'store')->middleware('can:meta_ads.draft')->name('store');
        Route::prefix('{id}')->whereNumber('id')->group(function () {
            Route::get('/', 'show')->middleware('can:meta_ads.view')->name('show');
            Route::get('edit', 'edit')->middleware('can:meta_ads.draft')->name('edit');
            Route::put('/', 'update')->middleware('can:meta_ads.draft')->name('update');
            Route::post('submit', 'submit')->middleware('can:meta_ads.draft')->name('submit');
            Route::post('archive', 'archive')->middleware('can:meta_ads.draft')->name('archive');
            Route::post('approve', 'approve')->middleware('can:meta_ads.approve')->name('approve');
            Route::post('reject', 'reject')->middleware('can:meta_ads.approve')->name('reject');
            Route::post('publish', 'publish')->middleware(['can:meta_ads.publish', 'throttle:10,1'])->name('publish');
        });
    });

    // Gift Finder admin (moved from /admin/gift-finder; old URLs 301 here)
    Route::prefix('gift-finder')->name('gift-finder.')->controller(GiftFinderController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:gift_finder.view')->name('index');
        Route::get('tags', 'tags')->middleware('can:gift_finder.view')->name('tags');
        Route::post('tags', 'saveTags')->middleware('can:gift_finder.manage')->name('tags.save');
        Route::post('tags/suggest', 'suggestTags')->middleware('can:gift_finder.manage')->name('tags.suggest');
    });

    // n8n integration API clients (moved from /admin/integration-clients; old URLs 301 here)
    Route::prefix('integration-clients')->name('integration-clients.')->controller(IntegrationClientController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:integration_clients.view')->name('index');
        Route::get('datatable', 'datatable')->middleware('can:integration_clients.view')->name('datatable');
        Route::post('/', 'store')->middleware('can:integration_clients.manage')->name('store');
        Route::put('{id}', 'update')->whereNumber('id')->middleware('can:integration_clients.manage')->name('update');
        Route::post('{id}/rotate', 'rotate')->whereNumber('id')->middleware('can:integration_clients.manage')->name('rotate');
        Route::post('{id}/revoke', 'revoke')->whereNumber('id')->middleware('can:integration_clients.manage')->name('revoke');
    });

    // Marketing tools (owner decision Q1: in both panels). Same controllers / services / views as /admin through thin subclasses
    // (App\Http\Controllers\Marketer\Tools\*), own route names marketer.{uri}.*, gated by the marketer guard's own permissions.
    foreach ([
        'banners' => Tools\BannerController::class, 'discount-codes' => Tools\DiscountCodeController::class, 'flash-sales' => Tools\FlashSaleController::class,
        'bundles' => Tools\BundleController::class, 'blog-posts' => Tools\BlogPostController::class, 'static-pages' => Tools\StaticPageController::class,
        'custom-pages' => Tools\CustomPageController::class,
    ] as $uri => $ctl) {
        Route::get("$uri/datatable", [$ctl, 'datatable'])->name("$uri.datatable");
        Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
        Route::resource($uri, $ctl)->except('show')->parameters([$uri => 'id']);
    }
    Route::get('bundles/{id}/qr', [Tools\BundleController::class, 'qr'])->whereNumber('id')->middleware('can:bundles.view')->name('bundles.qr');
    Route::post('bundles/{id}/qr/regenerate', [Tools\BundleController::class, 'regenerateQr'])->whereNumber('id')->middleware('can:bundles.edit')->name('bundles.qr.regenerate');

    Route::get('seo', [Tools\SeoSettingsController::class, 'edit'])->name('seo.edit');
    Route::put('seo', [Tools\SeoSettingsController::class, 'update'])->name('seo.update');
    Route::post('seo/validate', [Tools\SeoSettingsController::class, 'validateForm'])->name('seo.validate');
    Route::get('affiliates/datatable', [Tools\AffiliateController::class, 'datatable'])->name('affiliates.datatable');
    Route::get('affiliates', [Tools\AffiliateController::class, 'index'])->name('affiliates.index');
    Route::post('affiliates/{id}/approve', [Tools\AffiliateController::class, 'approve'])->whereNumber('id')->name('affiliates.approve');
    Route::post('affiliates/{id}/suspend', [Tools\AffiliateController::class, 'suspend'])->whereNumber('id')->name('affiliates.suspend');
    Route::delete('affiliates/{id}', [Tools\AffiliateController::class, 'destroy'])->whereNumber('id')->name('affiliates.destroy');
    Route::get('product-lists', [Tools\ProductListController::class, 'index'])->name('product-lists.index');
    Route::post('product-lists/{type}/validate', [Tools\ProductListController::class, 'validateForm'])->name('product-lists.validate');
    Route::put('product-lists/{type}', [Tools\ProductListController::class, 'update'])->name('product-lists.update');
    Route::get('reviews/datatable', [Tools\ReviewController::class, 'datatable'])->name('reviews.datatable');
    Route::get('reviews', [Tools\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{id}/approve', [Tools\ReviewController::class, 'approve'])->whereNumber('id')->name('reviews.approve');
    Route::post('reviews/{id}/reject', [Tools\ReviewController::class, 'reject'])->whereNumber('id')->name('reviews.reject');
    Route::delete('reviews/{id}', [Tools\ReviewController::class, 'destroy'])->whereNumber('id')->name('reviews.destroy');

    // Marketer accounts (own table `marketers`, roles marketer / marketer-owner); the admin panel never manages them
    Route::prefix('accounts')->name('accounts.')->controller(MarketerAccountController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:marketers.view')->name('index');
        Route::middleware('can:marketers.manage')->group(function () {
            Route::get('create', 'create')->name('create');
            Route::post('validate/{id?}', 'validateForm')->whereNumber('id')->name('validate');
            Route::post('/', 'store')->name('store');
            Route::get('{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('{id}', 'update')->whereNumber('id')->name('update');
            Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
        });
    });

    // Marketing internal API service tokens (MCP server) + their request log
    Route::prefix('api-tokens')->name('tokens.')->middleware('can:marketing.tokens.manage')->controller(MarketingTokenController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('datatable', 'datatable')->name('datatable');
        Route::post('validate', 'validateForm')->name('validate');
        Route::post('/', 'store')->name('store');
        Route::post('{id}/revoke', 'revoke')->whereNumber('id')->name('revoke');
    });
    Route::prefix('api-log')->name('api-log.')->middleware('can:marketing.audit.view')->controller(ApiLogController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('datatable', 'datatable')->name('datatable');
    });
});

// Short-lived signed preview of a private library file (P6): for the MCP server / API consumers (URL from GET /api/internal/marketing/media/*).
// No session: the signature (+ expiry, + version) is the authorisation. Responses are private, no-store, noindex.
Route::get('marketer/media-preview/{id}', [MediaLibraryController::class, 'signedPreview'])->whereNumber('id')->middleware(['signed', 'throttle:60,1'])->name('marketing-media.preview');

// AI Casting Studio (virtual models, casting sessions, ad workflow). Each route has its own ai_casting.* permission; files are streamed from the private disk only.
Route::prefix('marketer/casting')->middleware(['admin.locale:marketer', 'auth:marketer', 'can:marketing.panel'])->name('marketer.casting.')->group(function () {
    $c = \App\Http\Controllers\Marketer\CastingController::class;
    $m = \App\Http\Controllers\Marketer\CastingModelController::class;
    $s = \App\Http\Controllers\Marketer\CastingStudioController::class;
    Route::get('/', [$c, 'index'])->middleware('can:ai_casting.view')->name('index');
    Route::post('sessions', [$c, 'store'])->middleware(['can:ai_casting.create', 'throttle:10,1'])->name('sessions.store');
    Route::get('sessions/{id}', [$c, 'show'])->whereNumber('id')->middleware('can:ai_casting.view')->name('show');
    Route::get('sessions/{id}/status', [$c, 'status'])->whereNumber('id')->middleware('can:ai_casting.view')->name('status');
    Route::prefix('candidates/{id}')->whereNumber('id')->group(function () use ($c) {
        Route::get('image', [$c, 'image'])->middleware('can:ai_casting.view')->name('candidates.image');
        Route::post('regenerate', [$c, 'regenerate'])->middleware(['can:ai_casting.edit', 'throttle:20,1'])->name('candidates.regenerate');
        Route::put('/', [$c, 'update'])->middleware('can:ai_casting.edit')->name('candidates.update');
        Route::post('reject', [$c, 'reject'])->middleware('can:ai_casting.edit')->name('candidates.reject');
        Route::post('approve', [$c, 'approve'])->middleware('can:ai_casting.approve')->name('candidates.approve');
    });
    Route::prefix('models')->name('models.')->group(function () use ($m) {
        Route::get('/', [$m, 'index'])->middleware('can:ai_casting.view')->name('index');
        Route::get('create', [$m, 'create'])->middleware('can:ai_casting.create')->name('create');
        Route::post('/', [$m, 'store'])->middleware('can:ai_casting.create')->name('store');
        Route::prefix('{id}')->whereNumber('id')->group(function () use ($m) {
            Route::get('/', [$m, 'show'])->middleware('can:ai_casting.view')->name('show');
            Route::put('/', [$m, 'update'])->middleware('can:ai_casting.edit')->name('update');
            Route::post('duplicate', [$m, 'duplicate'])->middleware('can:ai_casting.create')->name('duplicate');
            Route::post('archive', [$m, 'archive'])->middleware('can:ai_casting.delete')->name('archive');
            Route::post('restore', [$m, 'restore'])->middleware('can:ai_casting.delete')->name('restore');
            Route::post('approve', [$m, 'approve'])->middleware('can:ai_casting.approve')->name('approve');
            Route::post('references', [$m, 'addReference'])->middleware(['can:ai_casting.edit', 'throttle:20,1'])->name('references.store');
            Route::get('references/{rid}/image', [$m, 'referenceImage'])->whereNumber('rid')->middleware('can:ai_casting.view')->name('references.image');
            Route::delete('references/{rid}', [$m, 'deleteReference'])->whereNumber('rid')->middleware('can:ai_casting.edit')->name('references.destroy');
        });
    });
    $v = \App\Http\Controllers\Marketer\CastingVideoController::class;
    Route::get('video', [$v, 'index'])->middleware('can:ai_casting.view')->name('video');
    Route::post('video', [$v, 'store'])->middleware(['can:ai_casting.create', 'throttle:10,1'])->name('video.store');
    Route::post('video/prompt', [$v, 'prompt'])->middleware('can:ai_casting.view')->name('video.prompt');
    Route::post('generations/{id}/retry', [$v, 'retry'])->whereNumber('id')->middleware(['can:ai_casting.create', 'throttle:10,1'])->name('generations.retry');
    Route::get('generations/{id}/source', [$v, 'source'])->whereNumber('id')->middleware('can:ai_casting.view')->name('generations.source');
    Route::get('studio', [$s, 'index'])->middleware('can:ai_casting.view')->name('studio');
    Route::post('studio', [$s, 'store'])->middleware(['can:ai_casting.create', 'throttle:20,1'])->name('studio.store');
    Route::prefix('generations/{id}')->whereNumber('id')->group(function () use ($s) {
        Route::get('status', [$s, 'status'])->middleware('can:ai_casting.view')->name('generations.status');
        Route::get('image', [$s, 'image'])->middleware('can:ai_casting.view')->name('generations.image');
        Route::post('video', [$s, 'video'])->middleware(['can:ai_casting.create', 'throttle:10,1'])->name('generations.video');
        Route::post('push-live', [$s, 'pushLive'])->middleware(['can:ai_casting.approve', 'can:ai_media.upload', 'throttle:10,1'])->name('generations.push-live');
        Route::post('library', [$s, 'toLibrary'])->middleware(['can:ai_casting.approve', 'can:ai_media.upload'])->name('generations.library');
    });
});
