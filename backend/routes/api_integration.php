<?php

use App\Http\Controllers\Api\Integration\GiftFinderController;
use App\Http\Controllers\Api\Integration\HealthController;
use App\Http\Controllers\Api\Integration\PingController;
use Illuminate\Support\Facades\Route;

// n8n integration API: /api/integration/v1 (bootstrap/app.php; names integration.*). READ-ONLY since P2 (MARKETER_PLAN.md).
// Auth = bearer + HMAC (integration.auth:{scope}|{scope}); docs/api/integration.md. No endpoint writes anything.
Route::get('ping', PingController::class)->middleware('integration.auth')->name('ping');
Route::get('health', HealthController::class)->middleware('integration.auth')->name('health');

Route::middleware('integration.auth:read|gift_finder:read')->prefix('gift-finder')->name('gift-finder.')->controller(GiftFinderController::class)->group(function () {
    Route::get('stats', 'stats')->name('stats');
    Route::get('untagged', 'untagged')->name('untagged');
    Route::get('occasions', 'occasions')->name('occasions');
});
