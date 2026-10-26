<?php

use App\Http\Controllers\Access\AdminUserController;
use App\Http\Controllers\Access\AuditLogController;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\Tenant\AffiliateController;
use App\Http\Controllers\Tenant\AffiliatePayoutController;
use App\Http\Controllers\Tenant\AffiliateTierController;
use App\Http\Controllers\Tenant\BannerController;
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
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\NotificationSettingsController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\PackagingOptionController;
use App\Http\Controllers\Tenant\PaymentMethodController;
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
use App\Http\Controllers\Tenant\AiChatController;
use App\Http\Controllers\Tenant\AiGeneratorController;
use App\Http\Controllers\Tenant\AiStudioController;
use App\Http\Controllers\Tenant\AiMarketTrendsController;
use App\Http\Controllers\Tenant\AiTrendsController;
use App\Http\Controllers\Tenant\AiProductEditorController;
use App\Http\Controllers\Tenant\AiSettingsController;
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
            'affiliate-tiers' => AffiliateTierController::class, 'custom-pages' => CustomPageController::class, 'static-pages' => StaticPageController::class, 'blog-posts' => \App\Http\Controllers\Tenant\BlogPostController::class,
        ] as $uri => $ctl) {
            Route::get("$uri/datatable", [$ctl, 'datatable'])->name("$uri.datatable");
            Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
            Route::resource($uri, $ctl)->except('show')->parameters([$uri => 'id']);
        }

        Route::get('notification-settings', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
        Route::put('notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
        Route::get('cod-fees', [CodFeeSettingsController::class, 'edit'])->name('cod-fees.edit');
        Route::put('cod-fees', [CodFeeSettingsController::class, 'update'])->name('cod-fees.update');
        Route::post('cod-fees/validate', [CodFeeSettingsController::class, 'validateForm'])->name('cod-fees.validate');

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

        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
