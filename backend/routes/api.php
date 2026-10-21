<?php

use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

// Loaded on the central domain under /api/v1 (bootstrap/app.php).
Route::get('tenants', [TenantController::class, 'index'])->middleware('throttle:60,1')->name('api.v1.tenants');
