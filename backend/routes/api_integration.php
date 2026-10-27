<?php

use App\Http\Controllers\Api\Integration\ApprovalProposalController;
use App\Http\Controllers\Api\Integration\EventController;
use App\Http\Controllers\Api\Integration\ExperimentResultController;
use App\Http\Controllers\Api\Integration\PingController;
use App\Http\Controllers\Api\Integration\MetricsController;
use Illuminate\Support\Facades\Route;

// n8n integration API: /api/integration/v1 (bootstrap/app.php). Auth = bearer + HMAC (integration.auth:{scope}). NO price/refund/stock/budget endpoints exist.
Route::get('ping', PingController::class)->middleware('integration.auth')->name('ping');
Route::get('events', [EventController::class, 'index'])->middleware('integration.auth:events:read')->name('events.index');
Route::get('approvals/{id}', [ApprovalProposalController::class, 'show'])->middleware('integration.auth:read')->whereNumber('id')->name('approvals.show');
Route::post('proposals', [ApprovalProposalController::class, 'store'])->middleware('integration.auth:proposals:write')->name('proposals.store');
Route::post('experiments/{id}/results', [ExperimentResultController::class, 'store'])->middleware('integration.auth:ingest:write')->whereNumber('id')->name('experiments.results');

// N1 read API (scope `read`; aggregate, no PII).
Route::middleware('integration.auth:read')->controller(MetricsController::class)->group(function () {
    Route::get('products', 'products')->name('products.index');
    Route::get('products/{id}/facts', 'facts')->whereNumber('id')->name('products.facts');
    Route::get('inventory/velocity', 'velocity')->name('inventory.velocity');
    Route::get('orders/summary', 'ordersSummary')->name('orders.summary');
    Route::get('orders/attribution', 'attribution')->name('orders.attribution');
    Route::get('returns/reasons', 'returnReasons')->name('returns.reasons');
    Route::get('bundles', 'bundles')->name('bundles.index');
    Route::get('customers/segments', 'segments')->name('customers.segments');
    Route::get('metrics/summary', 'summary')->name('metrics.summary');
    Route::get('metrics/products', 'metricsProducts')->name('metrics.products');
    Route::get('metrics/channels', 'metricsChannels')->name('metrics.channels');
    Route::get('brief/daily', 'daily')->name('brief.daily');
    Route::get('brief/weekly', 'weekly')->name('brief.weekly');
});
