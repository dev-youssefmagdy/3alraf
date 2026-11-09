<?php

use App\Http\Controllers\Marketer\AnalyticsController;
use App\Http\Controllers\Marketer\ApiLogController;
use App\Http\Controllers\Marketer\DashboardController;
use App\Http\Controllers\Marketer\GiftFinderController;
use App\Http\Controllers\Marketer\IntegrationClientController;
use App\Http\Controllers\Marketer\MarketingTokenController;
use App\Http\Controllers\Marketer\MediaLibraryController;
use App\Http\Controllers\Marketer\MetaAdsController;
use App\Http\Controllers\Marketer\SuggestionController;
use App\Enums\Marketing\SuggestionTypeKey;
use Illuminate\Support\Facades\Route;

// Marketer panel /marketer (MARKETER_PLAN.md P4): guard `admin` (same login as /admin), session locale like the admin, names marketer.*.
// Panel gate `marketing.panel` (any marketing permission) + one `can:` per route. Nav: App\Support\Marketing\MarketerNav (P5/P6/P7 add items there).
Route::prefix('marketer')->middleware(['admin.locale', 'auth:admin', 'can:marketing.panel'])->name('marketer.')->group(function () {
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
