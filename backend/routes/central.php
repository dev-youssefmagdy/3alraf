<?php

use App\Http\Controllers\Access\AdminUserController;
use App\Http\Controllers\Access\AuditLogController;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\Central\AttributeController;
use App\Http\Controllers\Central\BrandController;
use App\Http\Controllers\Central\CategoryController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\LandingController;
use App\Http\Controllers\Central\LanguageController;
use App\Http\Controllers\Central\ProductController;
use App\Http\Controllers\Central\ReportController;
use App\Http\Controllers\Central\TagController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Central\Ai\ModelController as AiModelController;
use App\Http\Controllers\Central\Ai\ProviderController as AiProviderController;
use App\Http\Controllers\Central\Ai\TenantAiController;
use App\Http\Controllers\Central\Ai\UsageController as AiUsageController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('central.landing');
Route::get('robots.txt', \App\Http\Controllers\Central\RobotsController::class)->name('central.robots');
Route::get('choose/{slug}', [LandingController::class, 'choose'])->where('slug', '[a-z0-9-]+')->middleware('throttle:60,1')->name('central.choose');

Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:auth')->name('central.google.callback');

Route::prefix('admin')->middleware('admin.locale')->name('central.')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('login', [\App\Http\Controllers\Auth\AdminLoginController::class, 'show'])->name('login');
        Route::post('login/validate', [\App\Http\Controllers\Auth\AdminLoginController::class, 'validateForm'])->middleware('throttle:login-validate')->name('login.validate');
        Route::post('login', [\App\Http\Controllers\Auth\AdminLoginController::class, 'login'])->middleware('throttle:auth')->name('login.submit');
        Route::get('login/otp', [\App\Http\Controllers\Auth\AdminLoginController::class, 'otpForm'])->name('login.otp');
        Route::post('login/otp', [\App\Http\Controllers\Auth\AdminLoginController::class, 'verifyOtp'])->middleware('throttle:auth')->name('login.otp.verify');
        Route::post('login/otp/resend', [\App\Http\Controllers\Auth\AdminLoginController::class, 'resendOtp'])->middleware('throttle:auth-strict')->name('login.otp.resend');
    });

    Route::middleware('auth:central')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Auth\AdminLoginController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('switch-tenant', [DashboardController::class, 'switchTenant'])->name('switch-tenant');
        Route::get('locale/{code}', function (string $code) {
            session()->put('admin_locale', $code);

            return back();
        })->name('locale');

        // Roles & staff accounts (Access\*Controller, guard from route-name prefix)
        foreach (['roles' => RoleController::class, 'admins' => AdminUserController::class] as $uri => $ctl) {
            Route::post("$uri/validate/{id?}", [$ctl, 'validateForm'])->name("$uri.validate");
            Route::resource($uri, $ctl)->except('show')->parameters([$uri => 'id']);
        }

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');

        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            $c = \App\Http\Controllers\Whatsapp\ConnectionController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::get('status', [$c, 'status'])->name('status');
            Route::post('connect', [$c, 'connect'])->middleware('throttle:10,1')->name('connect');
            Route::post('reconnect', [$c, 'reconnect'])->middleware('throttle:10,1')->name('reconnect');
            Route::post('disconnect', [$c, 'disconnect'])->middleware('throttle:10,1')->name('disconnect');
            Route::post('test', [$c, 'sendTest'])->middleware('throttle:10,1')->name('test');
            Route::put('settings', [$c, 'updateSettings'])->name('settings');
        });

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/datatable', [AuditLogController::class, 'datatable'])->name('audit.datatable');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z-]+')->name('reports.export');

        Route::get('languages/datatable', [LanguageController::class, 'datatable'])->name('languages.datatable');
        Route::post('languages/validate/{language?}', [LanguageController::class, 'validateForm'])->name('languages.validate');
        Route::resource('languages', LanguageController::class)->except('show');

        Route::get('barcode/{code}', BarcodeController::class)->where('code', '[0-9]{8,13}')->name('barcode');

        foreach (['brands' => [BrandController::class, 'brand'], 'tags' => [TagController::class, 'tag'], 'attributes' => [AttributeController::class, 'attribute'], 'products' => [ProductController::class, 'product']] as $name => [$ctl, $param]) {
            Route::get("$name/datatable", [$ctl, 'datatable'])->name("$name.datatable");
            Route::post("$name/validate/{{$param}?}", [$ctl, 'validateForm'])->name("$name.validate");
            Route::resource($name, $ctl)->except('show');
        }
        Route::post('categories/validate/{category?}', [CategoryController::class, 'validateForm'])->name('categories.validate');
        Route::resource('categories', CategoryController::class)->except('show');

        Route::prefix('ai')->name('ai.')->group(function () {
            foreach (['providers' => [AiProviderController::class, 'ai_provider'], 'models' => [AiModelController::class, 'ai_model']] as $name => [$ctl, $param]) {
                Route::get("$name/datatable", [$ctl, 'datatable'])->name("$name.datatable");
                Route::post("$name/validate/{{$param}?}", [$ctl, 'validateForm'])->name("$name.validate");
                Route::resource($name, $ctl)->except('show')->parameters([$name => $param]);
            }
            Route::post('providers/{ai_provider}/test', [AiProviderController::class, 'test'])->middleware('throttle:10,1')->name('providers.test');
            Route::get('tenants', [TenantAiController::class, 'index'])->name('tenants.index');
            Route::get('tenants/datatable', [TenantAiController::class, 'datatable'])->name('tenants.datatable');
            Route::post('tenants/{tenant}/validate', [TenantAiController::class, 'validateForm'])->name('tenants.validate');
            Route::get('tenants/{tenant}/edit', [TenantAiController::class, 'edit'])->name('tenants.edit');
            Route::put('tenants/{tenant}', [TenantAiController::class, 'update'])->name('tenants.update');
            Route::get('usage', [AiUsageController::class, 'index'])->name('usage.index');
            Route::get('usage/datatable', [AiUsageController::class, 'datatable'])->name('usage.datatable');
        });

        Route::get('tenants/datatable', [TenantController::class, 'datatable'])->name('tenants.datatable');
        Route::post('tenants/validate/{tenant?}', [TenantController::class, 'validateForm'])->name('tenants.validate');
        Route::put('tenants/{tenant}/growth', [TenantController::class, 'growth'])->name('tenants.growth');
        Route::resource('tenants', TenantController::class)->except('show');
    });
});
