<?php

use App\Http\Controllers\Access\AdminUserController;
use App\Http\Controllers\Access\AuditLogController;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\Tenant\AffiliateController;
use App\Http\Controllers\Tenant\AffiliatePayoutController;
use App\Http\Controllers\Tenant\AffiliateTierController;
use App\Http\Controllers\Tenant\AiChatController;
use App\Http\Controllers\Tenant\AiGeneratorController;
use App\Http\Controllers\Tenant\AiMarketTrendsController;
use App\Http\Controllers\Tenant\AiProductEditorController;
use App\Http\Controllers\Tenant\AiSettingsController;
use App\Http\Controllers\Tenant\AiStudioController;
use App\Http\Controllers\Tenant\AiTrendsController;
use App\Http\Controllers\Tenant\BannerController;
use App\Http\Controllers\Tenant\BlogPostController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\BundleController;
use App\Http\Controllers\Tenant\CatalogController;
use App\Http\Controllers\Tenant\CityController;
use App\Http\Controllers\Tenant\CodFeeSettingsController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\CustomPageController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DiscountCodeController;
use App\Http\Controllers\Tenant\ExpenseCategoryController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\FlashSaleController;
use App\Http\Controllers\Tenant\Growth\AmbassadorAdminController;
use App\Http\Controllers\Tenant\Growth\ApprovalController;
use App\Http\Controllers\Tenant\Growth\BrandMemoryController;
use App\Http\Controllers\Tenant\Growth\BriefController;
use App\Http\Controllers\Tenant\Growth\BundleEngineController;
use App\Http\Controllers\Tenant\Growth\CapitalAdminController;
use App\Http\Controllers\Tenant\Growth\ClientController;
use App\Http\Controllers\Tenant\Growth\ContentController;
use App\Http\Controllers\Tenant\Growth\EngagementAdminController;
use App\Http\Controllers\Tenant\Growth\EventController;
use App\Http\Controllers\Tenant\Growth\ExperimentController;
use App\Http\Controllers\Tenant\Growth\GiftFinderAdminController;
use App\Http\Controllers\Tenant\Growth\LabController;
use App\Http\Controllers\Tenant\Growth\SeasonalController;
use App\Http\Controllers\Tenant\Growth\SettingsController;
use App\Http\Controllers\Tenant\Growth\SmallBatchController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\NotificationSettingsController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\PackagingOptionController;
use App\Http\Controllers\Tenant\PaymentMethodController;
use App\Http\Controllers\Tenant\PosController;
use App\Http\Controllers\Tenant\PrivateFileController;
use App\Http\Controllers\Tenant\ProductListController;
use App\Http\Controllers\Tenant\PurchaseController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\ReturnController;
use App\Http\Controllers\Tenant\ReviewController;
use App\Http\Controllers\Tenant\ShippingCompanyController;
use App\Http\Controllers\Tenant\StaticPageController;
use App\Http\Controllers\Tenant\StockAdjustmentController;
use App\Http\Controllers\Tenant\StockController;
use App\Http\Controllers\Tenant\StockTransferController;
use App\Http\Controllers\Tenant\SupplierController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('admin.locale')->name('tenant.')->group(function () {
    Route::middleware('guest:tenant')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login/validate', [LoginController::class, 'validateForm'])->middleware('throttle:login-validate')->name('login.validate');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:auth')->name('login.submit');
    });

    Route::middleware('auth:tenant')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('locale/{code}', function (string $code) {
            session()->put('admin_locale', $code);

            return back();
        })->name('locale');
        Route::get('barcode/{code}', BarcodeController::class)->where('code', '[0-9]{8,13}')->name('barcode');

        // Catalog: enable central products for this tenant (price / sale / cost / status)
        Route::get('catalog/datatable', [CatalogController::class, 'datatable'])->name('catalog.datatable');
        Route::get('catalog/available', [CatalogController::class, 'available'])->name('catalog.available');
        Route::post('catalog/bulk/validate', [CatalogController::class, 'validateBulk'])->name('catalog.bulk.validate');
        Route::post('catalog/bulk', [CatalogController::class, 'bulkStore'])->name('catalog.bulk.store');
        Route::get('catalog/{tenantProduct}/qr', [CatalogController::class, 'qr'])->name('catalog.qr');
        Route::post('catalog/{tenantProduct}/qr/regenerate', [CatalogController::class, 'regenerateQr'])->name('catalog.qr.regenerate');
        Route::get('bundles/{id}/qr', [BundleController::class, 'qr'])->whereNumber('id')->name('bundles.qr');
        Route::post('bundles/{id}/qr/regenerate', [BundleController::class, 'regenerateQr'])->whereNumber('id')->name('bundles.qr.regenerate');
        Route::post('catalog/validate/{tenantProduct}', [CatalogController::class, 'validateForm'])->name('catalog.validate');
        Route::resource('catalog', CatalogController::class)->only(['index', 'edit', 'update', 'destroy'])->parameters(['catalog' => 'tenantProduct']);

        // Phase 3: simple CRUD modules (ResourceController): datatable + validate + resource (param {id})
        foreach ([
            'cities' => CityController::class, 'branches' => BranchController::class, 'suppliers' => SupplierController::class,
            'shipping-companies' => ShippingCompanyController::class, 'payment-methods' => PaymentMethodController::class,
            'banners' => BannerController::class, 'expense-categories' => ExpenseCategoryController::class, 'expenses' => ExpenseController::class,
            'discount-codes' => DiscountCodeController::class, 'flash-sales' => FlashSaleController::class, 'bundles' => BundleController::class, 'packaging' => PackagingOptionController::class,
            'affiliate-tiers' => AffiliateTierController::class, 'custom-pages' => CustomPageController::class, 'static-pages' => StaticPageController::class, 'blog-posts' => BlogPostController::class,
        ] as $uri => $ctl) {
            Route::get("$uri/datatable", [$ctl, 'datatable'])->name("$uri.datatable");
            Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
            Route::resource($uri, $ctl)->except('show')->parameters([$uri => 'id']);
        }

        Route::get('notification-settings', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
        Route::put('notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
        Route::get('seo', [\App\Http\Controllers\Tenant\SeoSettingsController::class, 'edit'])->name('seo.edit');
        Route::put('seo', [\App\Http\Controllers\Tenant\SeoSettingsController::class, 'update'])->name('seo.update');
        Route::post('seo/validate', [\App\Http\Controllers\Tenant\SeoSettingsController::class, 'validateForm'])->name('seo.validate');
        Route::get('cod-fees', [CodFeeSettingsController::class, 'edit'])->name('cod-fees.edit');
        Route::put('cod-fees', [CodFeeSettingsController::class, 'update'])->name('cod-fees.update');
        Route::post('cod-fees/validate', [CodFeeSettingsController::class, 'validateForm'])->name('cod-fees.validate');

        // n8n growth system (feature flag `growth`; perms growth.view|manage|approve)
        Route::middleware('growth.enabled')->prefix('growth')->name('growth.')->group(function () {
            Route::get('brief', [BriefController::class, 'index'])->middleware('growth.feature:brief')->name('brief.index');
            Route::post('brief/decision', [BriefController::class, 'decide'])->middleware('growth.feature:brief')->name('brief.decision');
            Route::get('brief/data', [BriefController::class, 'data'])->middleware('growth.feature:brief')->name('brief.data');
            Route::get('clients', [ClientController::class, 'index'])->middleware('growth.feature:integration_api')->name('clients.index');
            Route::get('clients/datatable', [ClientController::class, 'datatable'])->middleware('growth.feature:integration_api')->name('clients.datatable');
            Route::post('clients', [ClientController::class, 'store'])->middleware('growth.feature:integration_api')->name('clients.store');
            Route::put('clients/{id}', [ClientController::class, 'update'])->whereNumber('id')->middleware('growth.feature:integration_api')->name('clients.update');
            Route::post('clients/{id}/rotate', [ClientController::class, 'rotate'])->whereNumber('id')->middleware('growth.feature:integration_api')->name('clients.rotate');
            Route::post('clients/{id}/revoke', [ClientController::class, 'revoke'])->whereNumber('id')->middleware('growth.feature:integration_api')->name('clients.revoke');
            Route::get('events', [EventController::class, 'index'])->middleware('growth.feature:events_outbox')->name('events.index');
            Route::get('events/datatable', [EventController::class, 'datatable'])->middleware('growth.feature:events_outbox')->name('events.datatable');
            Route::post('events/{id}/replay', [EventController::class, 'replay'])->whereNumber('id')->middleware('growth.feature:events_outbox')->name('events.replay');
            Route::get('approvals', [ApprovalController::class, 'index'])->middleware('growth.feature:approvals')->name('approvals.index');
            Route::get('approvals/datatable', [ApprovalController::class, 'datatable'])->middleware('growth.feature:approvals')->name('approvals.datatable');
            Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve'])->whereNumber('id')->middleware('growth.feature:approvals')->name('approvals.approve');
            Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject'])->whereNumber('id')->middleware('growth.feature:approvals')->name('approvals.reject');
            Route::get('experiments', [ExperimentController::class, 'index'])->middleware('growth.feature:experiments')->name('experiments.index');
            Route::get('experiments/datatable', [ExperimentController::class, 'datatable'])->middleware('growth.feature:experiments')->name('experiments.datatable');
            Route::post('experiments', [ExperimentController::class, 'store'])->middleware('growth.feature:experiments')->name('experiments.store');
            Route::get('experiments/{id}', [ExperimentController::class, 'show'])->whereNumber('id')->middleware('growth.feature:experiments')->name('experiments.show');
            Route::put('experiments/{id}', [ExperimentController::class, 'update'])->whereNumber('id')->middleware('growth.feature:experiments')->name('experiments.update');
            Route::delete('experiments/{id}', [ExperimentController::class, 'destroy'])->whereNumber('id')->middleware('growth.feature:experiments')->name('experiments.destroy');
            Route::post('experiments/{id}/results', [ExperimentController::class, 'storeResult'])->whereNumber('id')->middleware('growth.feature:experiments')->name('experiments.results');
            $bm = BrandMemoryController::class;
            Route::get('brand-memory', [$bm, 'index'])->middleware('growth.feature:brand_memory')->name('brand-memory.index');
            Route::post('brand-memory', [$bm, 'store'])->middleware('growth.feature:brand_memory')->name('brand-memory.store');
            Route::put('brand-memory/{id}', [$bm, 'update'])->whereNumber('id')->middleware('growth.feature:brand_memory')->name('brand-memory.update');
            Route::delete('brand-memory/{id}', [$bm, 'destroy'])->whereNumber('id')->middleware('growth.feature:brand_memory')->name('brand-memory.destroy');
            Route::get('brand-memory/{id}/versions', [$bm, 'versions'])->whereNumber('id')->middleware('growth.feature:brand_memory')->name('brand-memory.versions');
            Route::post('claims', [$bm, 'storeClaim'])->middleware('growth.feature:brand_memory')->name('claims.store');
            Route::delete('claims/{id}', [$bm, 'destroyClaim'])->whereNumber('id')->middleware('growth.feature:brand_memory')->name('claims.destroy');
            $ct = ContentController::class;
            Route::get('content', [$ct, 'index'])->middleware('growth.feature:content_factory')->name('content.index');
            Route::post('content/schedule', [$ct, 'schedule'])->middleware('growth.feature:content_factory')->name('content.schedule');
            Route::post('content/{id}/publish', [$ct, 'publish'])->whereNumber('id')->middleware('growth.feature:content_factory')->name('content.publish');
            Route::post('content/{id}/skip', [$ct, 'skip'])->whereNumber('id')->middleware('growth.feature:content_factory')->name('content.skip');
            $be = BundleEngineController::class;
            Route::get('bundles', [$be, 'index'])->middleware('growth.feature:bundle_engine')->name('bundles.index');
            Route::post('bundles/propose', [$be, 'propose'])->middleware('growth.feature:bundle_engine')->name('bundles.propose');
            Route::post('bundles/ab', [$be, 'abStart'])->middleware('growth.feature:bundle_ab')->name('bundles.ab.start');
            Route::post('bundles/ab/{id}/conclude', [$be, 'abConclude'])->whereNumber('id')->middleware('growth.feature:bundle_ab')->name('bundles.ab.conclude');
            $en = EngagementAdminController::class;
            Route::get('interactions', [$en, 'inbox'])->middleware('growth.feature:interactions')->name('interactions.index');
            Route::get('interactions/datatable', [$en, 'inboxData'])->middleware('growth.feature:interactions')->name('interactions.datatable');
            Route::post('interactions/level', [$en, 'level'])->middleware('growth.feature:interactions')->name('interactions.level');
            Route::post('interactions/{id}/review', [$en, 'review'])->whereNumber('id')->middleware('growth.feature:interactions')->name('interactions.review');
            Route::post('interactions/{id}/action', [$en, 'action'])->whereNumber('id')->middleware('growth.feature:interactions')->name('interactions.action');
            Route::post('interactions/links', [$en, 'link'])->middleware('growth.feature:comment_to_cart')->name('interactions.links');
            Route::get('voc', [$en, 'voc'])->middleware('growth.feature:voc')->name('voc.index');
            Route::post('voc/generate', [$en, 'vocGenerate'])->middleware('growth.feature:voc')->name('voc.generate');
            Route::put('voc/tasks/{id}', [$en, 'taskUpdate'])->whereNumber('id')->middleware('growth.feature:voc')->name('voc.tasks.update');
            Route::get('reputation', [$en, 'reputation'])->middleware('growth.feature:reputation_radar')->name('reputation.index');
            Route::post('reputation/scan', [$en, 'reputationScan'])->middleware('growth.feature:reputation_radar')->name('reputation.scan');
            Route::post('reputation/{id}/resolve', [$en, 'reputationResolve'])->whereNumber('id')->middleware('growth.feature:reputation_radar')->name('reputation.resolve');
            Route::get('missed', [$en, 'missed'])->middleware('growth.feature:missed_opportunities')->name('missed.index');
            Route::post('missed/scan', [$en, 'missedScan'])->middleware('growth.feature:missed_opportunities')->name('missed.scan');
            Route::get('missed/datatable', [$en, 'missedData'])->middleware('growth.feature:missed_opportunities')->name('missed.datatable');
            Route::post('missed/{id}/status', [$en, 'missedStatus'])->whereNumber('id')->middleware('growth.feature:missed_opportunities')->name('missed.status');
            Route::put('missed/{id}', [$en, 'missedUpdate'])->whereNumber('id')->middleware('growth.feature:missed_opportunities')->name('missed.update');
            $sc = SeasonalController::class;
            Route::get('seasonal', [$sc, 'index'])->middleware('growth.feature:seasonal_calendar')->name('seasonal.index');
            Route::post('seasonal/year', [$sc, 'ensure'])->middleware('growth.feature:seasonal_calendar')->name('seasonal.year');
            Route::put('seasonal/{id}', [$sc, 'update'])->whereNumber('id')->middleware('growth.feature:seasonal_calendar')->name('seasonal.update');
            Route::post('seasonal/{id}/confirm', [$sc, 'confirm'])->whereNumber('id')->middleware('growth.feature:seasonal_calendar')->name('seasonal.confirm');
            Route::post('seasonal/tasks/{id}/toggle', [$sc, 'task'])->whereNumber('id')->middleware('growth.feature:seasonal_calendar')->name('seasonal.task');
            Route::post('seasonal/{id}/bundles', [$sc, 'bundles'])->whereNumber('id')->middleware(['growth.feature:seasonal_calendar', 'growth.feature:bundle_engine'])->name('seasonal.bundles');
            Route::post('seasonal/{id}/close', [$sc, 'close'])->whereNumber('id')->middleware('growth.feature:seasonal_calendar')->name('seasonal.close');
            Route::get('gift-finder', [GiftFinderAdminController::class, 'index'])->middleware('growth.feature:gift_finder')->name('gift-finder.index');
            $lb = LabController::class;
            Route::get('lab', [$lb, 'index'])->middleware('growth.feature:experiment_lab')->name('lab.index');
            Route::post('lab', [$lb, 'store'])->middleware('growth.feature:experiment_lab')->name('lab.store');
            Route::get('lab/{id}', [$lb, 'show'])->whereNumber('id')->middleware('growth.feature:experiment_lab')->name('lab.show');
            Route::post('lab/{id}/content', [$lb, 'approveContent'])->whereNumber('id')->middleware('growth.feature:experiment_lab')->name('lab.content');
            Route::post('lab/{id}/start', [$lb, 'start'])->whereNumber('id')->middleware('growth.feature:experiment_lab')->name('lab.start');
            Route::post('lab/{id}/links', [$lb, 'link'])->whereNumber('id')->middleware('growth.feature:experiment_lab')->name('lab.links');
            Route::post('lab/{id}/ads', [$lb, 'ads'])->whereNumber('id')->middleware('growth.feature:experiment_lab')->name('lab.ads');
            Route::post('lab/{id}/budget', [$lb, 'budget'])->whereNumber('id')->middleware(['growth.feature:experiment_lab', 'growth.feature:approvals'])->name('lab.budget');
            $cp = CapitalAdminController::class;
            Route::get('inventory', [$cp, 'inventory'])->middleware('growth.feature:inventory_engine')->name('inventory.index');
            Route::post('inventory/reorder', [$cp, 'reorder'])->middleware(['growth.feature:inventory_engine', 'growth.feature:approvals'])->name('inventory.reorder');
            Route::post('inventory/terms', [$cp, 'terms'])->middleware('growth.feature:inventory_engine')->name('inventory.terms');
            Route::post('inventory/prices', [$cp, 'savePrice'])->middleware('growth.feature:inventory_engine')->name('inventory.prices.save');
            Route::delete('inventory/prices/{id}', [$cp, 'deletePrice'])->whereNumber('id')->middleware('growth.feature:inventory_engine')->name('inventory.prices.delete');
            Route::post('inventory/story', [$cp, 'story'])->middleware('growth.feature:inventory_engine')->name('inventory.story');
            Route::get('returns-intel', [$cp, 'returns'])->middleware('growth.feature:return_intel')->name('returns-intel.index');
            Route::post('returns-intel/reason', [$cp, 'reason'])->middleware('growth.feature:return_intel')->name('returns-intel.reason');
            Route::post('returns-intel/tasks', [$cp, 'returnTasks'])->middleware('growth.feature:return_intel')->name('returns-intel.generate');
            Route::put('returns-intel/tasks/{id}', [$cp, 'returnTask'])->whereNumber('id')->middleware('growth.feature:return_intel')->name('returns-intel.task');
            Route::get('trends', [$cp, 'trends'])->middleware('growth.feature:trend_radar')->name('trends.index');
            Route::post('trends/scan', [$cp, 'trendScan'])->middleware('growth.feature:trend_radar')->name('trends.scan');
            Route::post('trends/{id}/test', [$cp, 'trendTest'])->whereNumber('id')->middleware('growth.feature:trend_radar')->name('trends.test');
            Route::post('trends/{id}/dismiss', [$cp, 'trendDismiss'])->whereNumber('id')->middleware('growth.feature:trend_radar')->name('trends.dismiss');
            Route::get('ceo', [$cp, 'ceo'])->middleware('growth.feature:ceo_challenge')->name('ceo.index');
            Route::post('ceo/generate', [$cp, 'ceoGenerate'])->middleware('growth.feature:ceo_challenge')->name('ceo.generate');
            Route::put('ceo/{id}', [$cp, 'ceoReview'])->whereNumber('id')->middleware('growth.feature:ceo_challenge')->name('ceo.review');
            Route::post('ceo/{id}/experiment', [$cp, 'ceoExperiment'])->whereNumber('id')->middleware('growth.feature:ceo_challenge')->name('ceo.experiment');
            $sb = SmallBatchController::class;
            Route::get('small-batch', [$sb, 'index'])->middleware('growth.feature:small_batch_lab')->name('batch.index');
            Route::post('small-batch', [$sb, 'store'])->middleware('growth.feature:small_batch_lab')->name('batch.store');
            Route::post('small-batch/{id}/open', [$sb, 'open'])->whereNumber('id')->middleware('growth.feature:small_batch_lab')->name('batch.open');
            Route::post('small-batch/{id}/decision', [$sb, 'decide'])->whereNumber('id')->middleware('growth.feature:small_batch_lab')->name('batch.decide');
            $am = AmbassadorAdminController::class;
            Route::get('ambassadors', [$am, 'index'])->middleware('growth.feature:ambassadors')->name('ambassadors.index');
            Route::post('ambassadors', [$am, 'enroll'])->middleware('growth.feature:ambassadors')->name('ambassadors.enroll');
            Route::put('ambassadors/{id}', [$am, 'update'])->whereNumber('id')->middleware('growth.feature:ambassadors')->name('ambassadors.update');
            Route::post('ambassadors/ideas', [$am, 'ideaStore'])->middleware('growth.feature:ambassadors')->name('ambassadors.ideas.store');
            Route::delete('ambassadors/ideas/{id}', [$am, 'ideaDestroy'])->whereNumber('id')->middleware('growth.feature:ambassadors')->name('ambassadors.ideas.destroy');
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::put('settings/features', [SettingsController::class, 'updateFeatures'])->name('settings.features');
        });

        Route::middleware('ai.enabled')->prefix('ai')->name('ai.')->group(function () {
            Route::get('settings', [AiSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [AiSettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/validate', [AiSettingsController::class, 'validateForm'])->name('settings.validate');

            Route::middleware('ai.enabled:trends')->prefix('trends')->name('trends.')->group(function () {
                Route::get('/', [AiTrendsController::class, 'index'])->name('index');
                Route::get('own', [AiTrendsController::class, 'own'])->name('own');
                Route::get('market', [AiTrendsController::class, 'market'])->name('market');
                Route::post('market/refresh', [AiTrendsController::class, 'refresh'])->name('refresh');
                Route::post('market/dismiss', [AiTrendsController::class, 'dismiss'])->name('dismiss');
                Route::post('purchase-draft', [AiTrendsController::class, 'purchaseDraft'])->name('draft');
                Route::get('export', [AiTrendsController::class, 'export'])->name('export');
            });

            Route::middleware('ai.enabled:trends')->prefix('market-trends')->name('market-trends.')->group(function () {
                Route::get('/', [AiMarketTrendsController::class, 'index'])->name('index');
                Route::get('data', [AiMarketTrendsController::class, 'data'])->name('data');
                Route::post('refresh', [AiMarketTrendsController::class, 'refresh'])->name('refresh');
                Route::post('dismiss', [AiMarketTrendsController::class, 'dismiss'])->name('dismiss');
                Route::get('export', [AiMarketTrendsController::class, 'export'])->name('export');
            });

            Route::middleware('ai.enabled:studio')->prefix('studio')->name('studio.')->group(function () {
                Route::get('/', [AiStudioController::class, 'index'])->name('index');
                Route::get('history', [AiStudioController::class, 'history'])->name('history');
                Route::post('generate', [AiStudioController::class, 'store'])->middleware('throttle:20,1')->name('store');
                Route::get('generations/{id}', [AiStudioController::class, 'show'])->whereNumber('id')->name('show');
                Route::post('generations/{id}/regenerate', [AiStudioController::class, 'regenerate'])->middleware('throttle:20,1')->whereNumber('id')->name('regenerate');
                Route::delete('generations/{id}', [AiStudioController::class, 'destroy'])->whereNumber('id')->name('destroy');
            });

            Route::middleware('ai.enabled:editor')->prefix('editor')->name('editor.')->group(function () {
                Route::get('/', [AiProductEditorController::class, 'index'])->name('index');
                Route::get('products', [AiProductEditorController::class, 'products'])->name('products');
                Route::get('history', [AiProductEditorController::class, 'history'])->name('history');
                Route::post('generate', [AiProductEditorController::class, 'store'])->middleware('throttle:20,1')->name('store');
                Route::get('generations/{id}', [AiProductEditorController::class, 'show'])->whereNumber('id')->name('show');
                Route::post('generations/{id}/apply', [AiProductEditorController::class, 'apply'])->whereNumber('id')->name('apply');
                Route::post('generations/{id}/reject', [AiProductEditorController::class, 'reject'])->whereNumber('id')->name('reject');
                Route::post('generations/{id}/undo', [AiProductEditorController::class, 'undo'])->whereNumber('id')->name('undo');
            });

            Route::middleware('ai.enabled:generators')->prefix('generators')->name('generators.')->group(function () {
                Route::get('/', [AiGeneratorController::class, 'index'])->name('index');
                Route::get('datatable', [AiGeneratorController::class, 'datatable'])->name('datatable');
                Route::post('generate', [AiGeneratorController::class, 'store'])->middleware('throttle:20,1')->name('store');
                Route::get('{id}', [AiGeneratorController::class, 'show'])->whereNumber('id')->name('show');
                Route::get('{id}/json', [AiGeneratorController::class, 'json'])->whereNumber('id')->name('json');
                Route::put('{id}', [AiGeneratorController::class, 'update'])->whereNumber('id')->name('update');
                Route::post('{id}/approve', [AiGeneratorController::class, 'approve'])->whereNumber('id')->name('approve');
                Route::post('{id}/reject', [AiGeneratorController::class, 'reject'])->whereNumber('id')->name('reject');
                Route::post('{id}/regenerate', [AiGeneratorController::class, 'regenerate'])->middleware('throttle:20,1')->whereNumber('id')->name('regenerate');
            });

            Route::middleware('ai.enabled:chat')->prefix('chat')->name('chat.')->group(function () {
                Route::get('/', [AiChatController::class, 'index'])->name('index');
                Route::post('stream', [AiChatController::class, 'stream'])->name('stream');
                Route::get('conversations/{id}', [AiChatController::class, 'show'])->whereNumber('id')->name('show');
                Route::put('conversations/{id}', [AiChatController::class, 'rename'])->whereNumber('id')->name('rename');
                Route::delete('conversations/{id}', [AiChatController::class, 'destroy'])->whereNumber('id')->name('destroy');
            });
        });

        Route::post('payment-methods/{id}/toggle', [PaymentMethodController::class, 'toggle'])->whereNumber('id')->name('payment-methods.toggle');

        // Roles & staff accounts (Access\*Controller, guard from route-name prefix)
        foreach (['roles' => RoleController::class, 'admins' => AdminUserController::class] as $uri => $ctl) {
            Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
            Route::resource($uri, $ctl)->except('show')->parameters([$uri => 'id']);
        }

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/datatable', [AuditLogController::class, 'datatable'])->name('audit.datatable');

        // Reports (view-only, date range + CSV export)
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');
        Route::post('reports/cod-settlement/settle', [ReportController::class, 'settleCod'])->name('reports.cod-settle');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z-]+')->name('reports.export');

        // Stock (read-only views)
        Route::get('stock/low', [\App\Http\Controllers\Tenant\LowStockController::class, 'index'])->name('stock.low');
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/datatable', [StockController::class, 'levels'])->name('stock.datatable');
        Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
        Route::get('stock/movements/datatable', [StockController::class, 'movementsData'])->name('stock.movements.datatable');

        // Stock documents: purchases, transfers, adjustments
        foreach (['purchases' => PurchaseController::class, 'stock-transfers' => StockTransferController::class, 'stock-adjustments' => StockAdjustmentController::class] as $uri => $ctl) {
            Route::get("$uri/datatable", [$ctl, 'datatable'])->name("$uri.datatable");
            Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
        }
        Route::resource('purchases', PurchaseController::class)->parameters(['purchases' => 'id']);
        Route::post('purchases/{id}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::post('purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
        Route::resource('stock-transfers', StockTransferController::class)->parameters(['stock-transfers' => 'id']);
        Route::post('stock-transfers/{id}/send', [StockTransferController::class, 'send'])->name('stock-transfers.send');
        Route::post('stock-transfers/{id}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
        Route::post('stock-transfers/{id}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
        Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store', 'show'])->parameters(['stock-adjustments' => 'id']);

        Route::get('files/{path}', [PrivateFileController::class, 'show'])->where('path', '.*')->middleware('signed')->name('files.private');

        // Phase 4: orders, returns, customers, notifications
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('pos/products', [PosController::class, 'products'])->name('pos.products');
        Route::get('pos/customers', [PosController::class, 'customers'])->name('pos.customers');
        Route::post('pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
        Route::post('pos/shipping', [PosController::class, 'shipping'])->name('pos.shipping');
        Route::post('pos/quote', [PosController::class, 'quote'])->name('pos.quote');
        Route::post('pos/orders', [PosController::class, 'store'])->name('pos.store');
        Route::get('orders/datatable', [OrderController::class, 'datatable'])->name('orders.datatable');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
        Route::get('orders/{id}/invoice.pdf', [OrderController::class, 'invoicePdf'])->whereNumber('id')->name('orders.invoice.pdf');
        Route::get('orders/{id}/invoice', [OrderController::class, 'invoice'])->whereNumber('id')->name('orders.invoice');
        Route::post('orders/{id}/status/validate', [OrderController::class, 'validateStatus'])->name('orders.status.validate');
        Route::post('orders/{id}/status', [OrderController::class, 'changeStatus'])->name('orders.status');
        Route::get('orders/{id}/proof', [OrderController::class, 'proofFile'])->whereNumber('id')->name('orders.proof.file');
        Route::post('orders/{id}/proof/approve', [OrderController::class, 'approveProof'])->name('orders.proof.approve');
        Route::post('orders/{id}/proof/reject', [OrderController::class, 'rejectProof'])->name('orders.proof.reject');
        Route::post('orders/{id}/returns/validate', [OrderController::class, 'validateReturn'])->name('orders.returns.validate');
        Route::post('orders/{id}/returns', [OrderController::class, 'createReturn'])->name('orders.returns.store');

        Route::get('returns/datatable', [ReturnController::class, 'datatable'])->name('returns.datatable');
        Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/{id}', [ReturnController::class, 'show'])->name('returns.show');
        Route::post('returns/{id}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
        Route::post('returns/{id}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
        Route::post('returns/{id}/refund', [ReturnController::class, 'refund'])->name('returns.refund');

        Route::get('customers/datatable', [CustomerController::class, 'datatable'])->name('customers.datatable');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{id}', [CustomerController::class, 'show'])->name('customers.show');

        // Phase 5 growth: affiliates, payouts, product-list pins, reviews
        Route::get('affiliates/datatable', [AffiliateController::class, 'datatable'])->name('affiliates.datatable');
        Route::get('affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::post('affiliates/{id}/approve', [AffiliateController::class, 'approve'])->name('affiliates.approve');
        Route::post('affiliates/{id}/suspend', [AffiliateController::class, 'suspend'])->name('affiliates.suspend');
        Route::delete('affiliates/{id}', [AffiliateController::class, 'destroy'])->name('affiliates.destroy');

        Route::get('affiliate-payouts/datatable', [AffiliatePayoutController::class, 'datatable'])->name('affiliate-payouts.datatable');
        Route::get('affiliate-payouts', [AffiliatePayoutController::class, 'index'])->name('affiliate-payouts.index');
        Route::post('affiliate-payouts/{id}/pay', [AffiliatePayoutController::class, 'pay'])->name('affiliate-payouts.pay');
        Route::post('affiliate-payouts/{id}/reject', [AffiliatePayoutController::class, 'reject'])->name('affiliate-payouts.reject');

        Route::get('product-lists', [ProductListController::class, 'index'])->name('product-lists.index');
        Route::post('product-lists/{type}/validate', [ProductListController::class, 'validateForm'])->name('product-lists.validate');
        Route::put('product-lists/{type}', [ProductListController::class, 'update'])->name('product-lists.update');

        Route::get('reviews/datatable', [ReviewController::class, 'datatable'])->name('reviews.datatable');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{id}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('reviews/{id}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
        Route::delete('reviews/{id}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('stock-requests/datatable', [\App\Http\Controllers\Tenant\StockRequestController::class, 'datatable'])->name('stock-requests.datatable');
        Route::get('stock-requests', [\App\Http\Controllers\Tenant\StockRequestController::class, 'index'])->name('stock-requests.index');
        Route::post('stock-requests/{id}/status/{status}', [\App\Http\Controllers\Tenant\StockRequestController::class, 'status'])->whereNumber('id')->whereIn('status', ['new', 'contacted', 'closed'])->name('stock-requests.status');
        Route::delete('stock-requests/{id}', [\App\Http\Controllers\Tenant\StockRequestController::class, 'destroy'])->whereNumber('id')->name('stock-requests.destroy');

        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
