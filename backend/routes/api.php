<?php

use App\Http\Controllers\Api\V1\LegacyStoreListController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 (bootstrap/app.php). The first route keeps its historical URI = deprecated alias for old app builds (returns the one shop).
Route::get('tenants', [LegacyStoreListController::class, 'index'])->middleware('throttle:60,1')->name('api.v1.legacy-stores');
Route::get('health', \App\Http\Controllers\Api\V1\HealthController::class)->middleware('throttle:120,1')->name('api.v1.health');
Route::get('app-config', \App\Http\Controllers\Api\V1\AppConfigController::class)->middleware('throttle:120,1')->name('api.v1.app-config');
