<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\Central\AttributeController;
use App\Http\Controllers\Central\BrandController;
use App\Http\Controllers\Central\CategoryController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\LandingController;
use App\Http\Controllers\Central\ProductController;
use App\Http\Controllers\Central\LanguageController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\ReportController;
use App\Http\Controllers\Access\AdminUserController;
use App\Http\Controllers\Access\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('central.landing');
Route::get('choose/{slug}', [LandingController::class, 'choose'])->where('slug', '[a-z0-9-]+')->middleware('throttle:60,1')->name('central.choose');

Route::prefix('admin')->middleware('admin.locale')->name('central.')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login/validate', [LoginController::class, 'validateForm'])->middleware('throttle:login-validate')->name('login.validate');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    });

    Route::middleware('auth:central')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
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

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z-]+')->name('reports.export');

        Route::get('languages/datatable', [LanguageController::class, 'datatable'])->name('languages.datatable');
        Route::post('languages/validate/{language?}', [LanguageController::class, 'validateForm'])->name('languages.validate');
        Route::resource('languages', LanguageController::class)->except('show');

        Route::get('barcode/{code}', BarcodeController::class)->where('code', '[0-9]{8,13}')->name('barcode');

        foreach (['brands' => [BrandController::class, 'brand'], 'attributes' => [AttributeController::class, 'attribute'], 'products' => [ProductController::class, 'product']] as $name => [$ctl, $param]) {
            Route::get("$name/datatable", [$ctl, 'datatable'])->name("$name.datatable");
            Route::post("$name/validate/{{$param}?}", [$ctl, 'validateForm'])->name("$name.validate");
            Route::resource($name, $ctl)->except('show');
        }
        Route::post('categories/validate/{category?}', [CategoryController::class, 'validateForm'])->name('categories.validate');
        Route::resource('categories', CategoryController::class)->except('show');

        Route::get('tenants/datatable', [TenantController::class, 'datatable'])->name('tenants.datatable');
        Route::post('tenants/validate/{tenant?}', [TenantController::class, 'validateForm'])->name('tenants.validate');
        Route::resource('tenants', TenantController::class)->except('show');
    });
});
