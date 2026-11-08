<?php

use App\Http\Controllers\Access\AdminUserController;
use App\Http\Controllers\Access\AuditLogController;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AffiliatePayoutController;
use App\Http\Controllers\Admin\AffiliateTierController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\BundleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CodFeeSettingsController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomPageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountCodeController;
use App\Http\Controllers\Admin\Erp\AccountController as ErpAccountController;
use App\Http\Controllers\Admin\Erp\AnomalyController as ErpAnomalyController;
use App\Http\Controllers\Admin\Erp\AssetController as ErpAssetController;
use App\Http\Controllers\Admin\Erp\AuditController;
use App\Http\Controllers\Admin\Erp\CashBankController;
use App\Http\Controllers\Admin\Erp\HubController as ErpHubController;
use App\Http\Controllers\Admin\Erp\JournalController as ErpJournalController;
use App\Http\Controllers\Admin\Erp\MigrationController;
use App\Http\Controllers\Admin\Erp\OwnerController as ErpOwnerController;
use App\Http\Controllers\Admin\Erp\PartyController as ErpPartyController;
use App\Http\Controllers\Admin\Erp\PeriodController as ErpPeriodController;
use App\Http\Controllers\Admin\Erp\ProfitController;
use App\Http\Controllers\Admin\Erp\ReportController as ErpReportController;
use App\Http\Controllers\Admin\Erp\SettingsController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\GeneralSettingsController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LowStockController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\NotificationSettingsController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PackagingOptionController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PrivateFileController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVideoController;
use App\Http\Controllers\Admin\ProductListController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SeoSettingsController;
use App\Http\Controllers\Admin\ShippingCompanyController;
use App\Http\Controllers\Admin\StaticPageController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\StockRequestController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\Whatsapp\ChatConversationController;
use App\Http\Controllers\Whatsapp\ChatMediaController;
use App\Http\Controllers\Whatsapp\ChatMessageController;
use App\Http\Controllers\Whatsapp\ChatPageController;
use App\Http\Controllers\Whatsapp\ConnectionController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('admin.locale')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminLoginController::class, 'show'])->name('login');
        Route::post('login/validate', [AdminLoginController::class, 'validateForm'])->middleware('throttle:login-validate')->name('login.validate');
        Route::post('login', [AdminLoginController::class, 'login'])->middleware('throttle:auth')->name('login.submit');
        Route::get('login/otp', [AdminLoginController::class, 'otpForm'])->name('login.otp');
        Route::post('login/otp', [AdminLoginController::class, 'verifyOtp'])->middleware('throttle:auth')->name('login.otp.verify');
        Route::post('login/otp/resend', [AdminLoginController::class, 'resendOtp'])->middleware('throttle:auth-strict')->name('login.otp.resend');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AdminLoginController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::view('welcome', 'admin.welcome')->name('welcome');
        Route::get('locale/{code}', function (string $code) {
            session()->put('admin_locale', $code);

            return back();
        })->name('locale');
        Route::get('barcode/{code}', BarcodeController::class)->where('code', '[0-9]{8,13}')->name('barcode');

        // Settings → General (shop profile + Gift Finder / customer-cancel switches)
        Route::get('settings/general', [GeneralSettingsController::class, 'edit'])->name('general-settings.edit');
        Route::post('settings/general/validate', [GeneralSettingsController::class, 'validateForm'])->name('general-settings.validate');
        Route::put('settings/general', [GeneralSettingsController::class, 'update'])->name('general-settings.update');

        // Languages + products / categories / brands / tags / attributes
        Route::get('languages/datatable', [LanguageController::class, 'datatable'])->name('languages.datatable');
        Route::post('languages/validate/{language?}', [LanguageController::class, 'validateForm'])->name('languages.validate');
        Route::resource('languages', LanguageController::class)->except('show');
        // Products: activate-many, bulk, QR, take off sale (declared before the resource so `available` is not read as {product})
        Route::post('products/bulk/validate', [ProductController::class, 'validateBulk'])->name('products.bulk.validate');
        Route::post('products/bulk', [ProductController::class, 'bulkStore'])->name('products.bulk.store');
        Route::get('products/{product}/qr', [ProductController::class, 'qr'])->whereNumber('product')->name('products.qr');
        Route::post('products/{product}/qr/regenerate', [ProductController::class, 'regenerateQr'])->whereNumber('product')->name('products.qr.regenerate');
        Route::post('products/{product}/unlist', [ProductController::class, 'unlist'])->whereNumber('product')->name('products.unlist');
        // Public gallery videos + shared image/video order (P8)
        Route::post('products/{product}/videos', [ProductVideoController::class, 'store'])->whereNumber('product')->name('products.videos.store');
        Route::delete('products/{product}/videos/{video}', [ProductVideoController::class, 'destroy'])->whereNumber(['product', 'video'])->scopeBindings()->name('products.videos.destroy');
        Route::post('products/{product}/media/order', [ProductVideoController::class, 'order'])->whereNumber('product')->name('products.media.order');
        // Old Catalog bookmarks: permanent redirect to the Products equivalents (still inside the admin auth group)
        Route::get('catalog/{path?}', fn (Request $r, ?string $path = null) => redirect()->to('/admin/products'.($path ? '/'.$path : '').($r->getQueryString() ? '?'.$r->getQueryString() : ''), 301))->where('path', '.*');
        foreach (['brands' => [BrandController::class, 'brand'], 'tags' => [TagController::class, 'tag'], 'attributes' => [AttributeController::class, 'attribute'], 'products' => [ProductController::class, 'product']] as $name => [$ctl, $param]) {
            Route::get("$name/datatable", [$ctl, 'datatable'])->name("$name.datatable");
            Route::post("$name/validate/{{$param}?}", [$ctl, 'validateForm'])->name("$name.validate");
            Route::resource($name, $ctl)->except('show');
        }
        Route::post('categories/validate/{category?}', [CategoryController::class, 'validateForm'])->name('categories.validate');
        Route::resource('categories', CategoryController::class)->except('show');

        // ERP anomalies (erp.audit.view)
        Route::get('erp/anomalies', [ErpAnomalyController::class, 'anomalies'])->name('erp.anomalies');
        Route::get('erp/anomalies/export', [ErpAnomalyController::class, 'exportAnomalies'])->name('erp.anomalies.export');

        Route::get('bundles/{id}/qr', [BundleController::class, 'qr'])->whereNumber('id')->name('bundles.qr');
        Route::post('bundles/{id}/qr/regenerate', [BundleController::class, 'regenerateQr'])->whereNumber('id')->name('bundles.qr.regenerate');

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

        // ERP / accounting: hub of cards, each card its own page
        Route::prefix('erp')->name('erp.')->group(function () {
            Route::get('/', [ErpHubController::class, 'index'])->name('hub');
            Route::get('accounts', [ErpAccountController::class, 'index'])->name('accounts.index');
            Route::post('accounts', [ErpAccountController::class, 'store'])->name('accounts.store');
            Route::delete('accounts/{id}', [ErpAccountController::class, 'destroy'])->where('id', '[0-9]{1,18}')->name('accounts.destroy');
            Route::post('accounts/{id}/restore', [ErpAccountController::class, 'restore'])->where('id', '[0-9]{1,18}')->name('accounts.restore');
            Route::get('journals', [ErpJournalController::class, 'index'])->name('journals.index');
            Route::get('journals/create', [ErpJournalController::class, 'create'])->name('journals.create');
            Route::post('journals', [ErpJournalController::class, 'store'])->name('journals.store');
            Route::get('journals/{id}', [ErpJournalController::class, 'show'])->where('id', '[0-9]{1,18}')->name('journals.show');
            Route::post('journals/{id}/post', [ErpJournalController::class, 'post'])->where('id', '[0-9]{1,18}')->name('journals.post');
            Route::post('journals/{id}/reverse', [ErpJournalController::class, 'reverse'])->where('id', '[0-9]{1,18}')->name('journals.reverse');
            Route::post('journals/{id}/repost', [ErpJournalController::class, 'repost'])->where('id', '[0-9]{1,18}')->name('journals.repost');
            Route::delete('journals/{id}', [ErpJournalController::class, 'destroy'])->where('id', '[0-9]{1,18}')->name('journals.destroy');
            Route::get('periods', [ErpPeriodController::class, 'index'])->name('periods.index');
            Route::post('periods/{id}/close', [ErpPeriodController::class, 'close'])->where('id', '[0-9]{1,18}')->name('periods.close');
            Route::post('periods/{id}/reopen', [ErpPeriodController::class, 'reopen'])->where('id', '[0-9]{1,18}')->name('periods.reopen');
            Route::post('periods/years/{id}/close', [ErpPeriodController::class, 'closeYear'])->where('id', '[0-9]{1,18}')->name('periods.year-close');
            Route::post('periods/years/{id}/reopen', [ErpPeriodController::class, 'reopenYear'])->where('id', '[0-9]{1,18}')->name('periods.year-reopen');
            // profit distribution + settings
            Route::get('profits', [ProfitController::class, 'index'])->name('profits.index');
            Route::post('profits', [ProfitController::class, 'store'])->name('profits.store');
            Route::get('profits/report', [ProfitController::class, 'report'])->name('profits.report');
            Route::get('profits/owner/{owner}/statement', [ProfitController::class, 'statement'])->where('owner', '[0-9]{1,18}')->name('profits.statement');
            Route::get('profits/{id}', [ProfitController::class, 'show'])->where('id', '[0-9]{1,18}')->name('profits.show');
            Route::post('profits/{id}/approve', [ProfitController::class, 'approve'])->where('id', '[0-9]{1,18}')->name('profits.approve');
            Route::post('profits/{id}/post', [ProfitController::class, 'post'])->where('id', '[0-9]{1,18}')->name('profits.post');
            Route::post('profits/{id}/cancel', [ProfitController::class, 'cancel'])->where('id', '[0-9]{1,18}')->name('profits.cancel');
            Route::post('profits/{id}/lines/{line}/pay', [ProfitController::class, 'pay'])->where(['id' => '[0-9]{1,18}', 'line' => '[0-9]{1,18}'])->name('profits.pay');
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/rates', [SettingsController::class, 'storeRate'])->name('settings.rates.store');
            Route::delete('settings/rates/{id}', [SettingsController::class, 'destroyRate'])->where('id', '[0-9]{1,18}')->name('settings.rates.destroy');
            Route::get('migration', [MigrationController::class, 'index'])->name('migration.index');
            Route::post('migration/reconcile', [MigrationController::class, 'reconcile'])->name('migration.reconcile');
            Route::post('migration/detect', [MigrationController::class, 'detect'])->name('migration.detect');
            Route::post('migration/post-missing', [MigrationController::class, 'postMissing'])->name('migration.post-missing');
            Route::put('migration/auto-posting', [MigrationController::class, 'toggle'])->name('migration.toggle');
            Route::get('dashboard', [App\Http\Controllers\Admin\Erp\DashboardController::class, 'index'])->name('dashboard');
            Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
            Route::get('cash-bank', [CashBankController::class, 'index'])->name('cash-bank.index');
            foreach (['vat', 'carrier', 'payout'] as $kind) {
                Route::post("cash-bank/$kind", [CashBankController::class, 'settle'])->defaults('kind', $kind)->name("cash-bank.$kind");
            }
            Route::post('posting-failures/{id}/retry', [MigrationController::class, 'retry'])->where('id', '[0-9]{1,18}')->name('migration.retry');
            Route::get('branches', [App\Http\Controllers\Admin\Erp\BranchController::class, 'index'])->name('branches.index');
            Route::post('branches/transfer', [App\Http\Controllers\Admin\Erp\BranchController::class, 'transfer'])->name('branches.transfer');
            Route::get('reports', [ErpReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}', [ErpReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');
            Route::get('reports/{report}/export', [ErpReportController::class, 'export'])->where('report', '[a-z-]+')->name('reports.export');
            // customers & suppliers ledgers
            Route::prefix('parties/{type}')->where(['type' => 'customer|supplier'])->name('parties.')->group(function () {
                Route::get('/', [ErpPartyController::class, 'index'])->name('index');
                Route::get('ageing', [ErpPartyController::class, 'ageing'])->name('ageing');
                Route::get('{id}', [ErpPartyController::class, 'show'])->where('id', '[0-9]{1,18}')->name('show');
                Route::post('{id}/settle', [ErpPartyController::class, 'settle'])->where('id', '[0-9]{1,18}')->name('settle');
                Route::post('{id}/apply-advance', [ErpPartyController::class, 'applyAdvance'])->where('id', '[0-9]{1,18}')->name('apply-advance');
                Route::post('{id}/note', [ErpPartyController::class, 'note'])->where('id', '[0-9]{1,18}')->name('note');
                Route::post('{id}/opening', [ErpPartyController::class, 'opening'])->where('id', '[0-9]{1,18}')->name('opening');
            });
            // owners & equity
            Route::get('owners', [ErpOwnerController::class, 'index'])->name('owners.index');
            Route::post('owners', [ErpOwnerController::class, 'store'])->name('owners.store');
            Route::post('owners/branch-allocation', [ErpOwnerController::class, 'allocate'])->name('owners.allocate');
            Route::get('owners/{id}', [ErpOwnerController::class, 'show'])->where('id', '[0-9]{1,18}')->name('owners.show');
            Route::put('owners/{id}', [ErpOwnerController::class, 'update'])->where('id', '[0-9]{1,18}')->name('owners.update');
            Route::post('owners/{id}/transactions', [ErpOwnerController::class, 'transact'])->where('id', '[0-9]{1,18}')->name('owners.transact');
            // fixed assets
            Route::get('assets', [ErpAssetController::class, 'index'])->name('assets.index');
            Route::post('assets', [ErpAssetController::class, 'store'])->name('assets.store');
            Route::post('assets/depreciate', [ErpAssetController::class, 'depreciate'])->name('assets.depreciate-all');
            Route::get('assets/{id}', [ErpAssetController::class, 'show'])->where('id', '[0-9]{1,18}')->name('assets.show');
            Route::post('assets/{id}/depreciate', [ErpAssetController::class, 'depreciate'])->where('id', '[0-9]{1,18}')->name('assets.depreciate');
            Route::post('assets/{id}/dispose', [ErpAssetController::class, 'dispose'])->where('id', '[0-9]{1,18}')->name('assets.dispose');
            Route::post('assets/{id}/transfer', [ErpAssetController::class, 'transfer'])->where('id', '[0-9]{1,18}')->name('assets.transfer');
        });

        Route::get('notification-settings', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
        Route::put('notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
        Route::get('seo', [SeoSettingsController::class, 'edit'])->name('seo.edit');
        Route::put('seo', [SeoSettingsController::class, 'update'])->name('seo.update');
        Route::post('seo/validate', [SeoSettingsController::class, 'validateForm'])->name('seo.validate');
        Route::get('cod-fees', [CodFeeSettingsController::class, 'edit'])->name('cod-fees.edit');
        Route::put('cod-fees', [CodFeeSettingsController::class, 'update'])->name('cod-fees.update');
        Route::post('cod-fees/validate', [CodFeeSettingsController::class, 'validateForm'])->name('cod-fees.validate');

        // Gift Finder admin + n8n integration clients moved to the marketer panel (P4): old bookmarks 301 to /marketer/... (GET only).
        foreach (['gift-finder', 'integration-clients'] as $moved) {
            Route::get("$moved/{path?}", fn (Request $r, ?string $path = null) => redirect()->to("/marketer/$moved".($path ? '/'.$path : '').($r->getQueryString() ? '?'.$r->getQueryString() : ''), 301))->where('path', '.*')->name("moved.$moved");
        }

        Route::post('payment-methods/{id}/toggle', [PaymentMethodController::class, 'toggle'])->whereNumber('id')->name('payment-methods.toggle');

        // Roles & staff accounts (Access\*Controller, guard from route-name prefix)
        foreach (['roles' => RoleController::class, 'admins' => AdminUserController::class] as $uri => $ctl) {
            Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
            Route::resource($uri, $ctl)->except('show')->parameters([$uri => 'id']);
        }

        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            $c = ConnectionController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::get('status', [$c, 'status'])->name('status');
            Route::post('connect', [$c, 'connect'])->middleware('throttle:10,1')->name('connect');
            Route::post('reconnect', [$c, 'reconnect'])->middleware('throttle:10,1')->name('reconnect');
            Route::post('disconnect', [$c, 'disconnect'])->middleware('throttle:10,1')->name('disconnect');
            Route::post('test', [$c, 'sendTest'])->middleware('throttle:10,1')->name('test');
            Route::put('settings', [$c, 'updateSettings'])->name('settings');
        });

        Route::prefix('whatsapp/chat')->name('whatsapp.chat.')->group(function () {
            $conv = ChatConversationController::class;
            $msg = ChatMessageController::class;
            $media = ChatMediaController::class;
            Route::get('/', [ChatPageController::class, 'index'])->name('index');
            Route::get('limits', [$conv, 'limits'])->middleware('throttle:120,1')->name('limits');
            Route::get('conversations', [$conv, 'index'])->middleware('throttle:120,1')->name('conversations.index');
            Route::post('conversations', [$conv, 'store'])->middleware('throttle:30,1')->name('conversations.store');
            Route::get('conversations/{id}', [$conv, 'show'])->whereNumber('id')->middleware('throttle:120,1')->name('conversations.show');
            Route::patch('conversations/{id}', [$conv, 'update'])->whereNumber('id')->middleware('throttle:60,1')->name('conversations.update');
            Route::delete('conversations/{id}', [$conv, 'destroy'])->whereNumber('id')->middleware('throttle:30,1')->name('conversations.destroy');
            Route::put('conversations/{id}/assign', [$conv, 'assign'])->whereNumber('id')->middleware('throttle:60,1')->name('conversations.assign');
            Route::post('conversations/{id}/read', [$conv, 'read'])->whereNumber('id')->middleware('throttle:120,1')->name('conversations.read');
            Route::post('conversations/{id}/presence', [$conv, 'presence'])->whereNumber('id')->middleware('throttle:60,1')->name('conversations.presence');
            Route::get('conversations/{conversation}/messages', [$msg, 'index'])->whereNumber('conversation')->middleware('throttle:120,1')->name('messages.index');
            Route::post('conversations/{conversation}/messages', [$msg, 'store'])->whereNumber('conversation')->middleware('throttle:60,1')->name('messages.store');
            Route::post('messages/{id}/resend', [$msg, 'resend'])->whereNumber('id')->middleware('throttle:30,1')->name('messages.resend');
            Route::post('messages/{id}/react', [$msg, 'react'])->whereNumber('id')->middleware('throttle:60,1')->name('messages.react');
            Route::put('messages/{id}', [$msg, 'update'])->whereNumber('id')->middleware('throttle:30,1')->name('messages.update');
            Route::delete('messages/{id}', [$msg, 'destroy'])->whereNumber('id')->middleware('throttle:30,1')->name('messages.destroy');
            Route::get('media/{id}', [$media, 'show'])->whereNumber('id')->middleware('throttle:240,1')->name('media.show');
        });

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/datatable', [AuditLogController::class, 'datatable'])->name('audit.datatable');

        // Reports (view-only, date range + CSV export)
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');
        Route::post('reports/cod-settlement/settle', [ReportController::class, 'settleCod'])->name('reports.cod-settle');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z-]+')->name('reports.export');

        // Stock (read-only views)
        Route::get('stock/low', [LowStockController::class, 'index'])->name('stock.low');
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

        // Phase 5 marketing: affiliates, payouts, product-list pins, reviews
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

        Route::get('stock-requests/datatable', [StockRequestController::class, 'datatable'])->name('stock-requests.datatable');
        Route::get('stock-requests', [StockRequestController::class, 'index'])->name('stock-requests.index');
        Route::post('stock-requests/{id}/status/{status}', [StockRequestController::class, 'status'])->whereNumber('id')->whereIn('status', ['new', 'contacted', 'closed'])->name('stock-requests.status');
        Route::delete('stock-requests/{id}', [StockRequestController::class, 'destroy'])->whereNumber('id')->name('stock-requests.destroy');

        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
