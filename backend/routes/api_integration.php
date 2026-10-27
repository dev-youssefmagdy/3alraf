<?php

use App\Http\Controllers\Api\Integration\ApprovalProposalController;
use App\Http\Controllers\Api\Integration\BrandMemoryController;
use App\Http\Controllers\Api\Integration\EventController;
use App\Http\Controllers\Api\Integration\ExperimentResultController;
use App\Http\Controllers\Api\Integration\PingController;
use App\Http\Controllers\Api\Integration\MetricsController;
use Illuminate\Support\Facades\Route;

// n8n integration API: /api/integration/v1 (bootstrap/app.php). Auth = bearer + HMAC (integration.auth:{scope}). NO price/refund/stock/budget endpoints exist.
Route::get('ping', PingController::class)->middleware('integration.auth')->name('ping');
Route::get('events', [EventController::class, 'index'])->middleware('integration.auth:events:read,events_outbox')->name('events.index');
Route::get('approvals/{id}', [ApprovalProposalController::class, 'show'])->middleware('integration.auth:read,approvals')->whereNumber('id')->name('approvals.show');
Route::post('proposals', [ApprovalProposalController::class, 'store'])->middleware('integration.auth:proposals:write,approvals')->name('proposals.store');
Route::post('experiments/{id}/results', [ExperimentResultController::class, 'store'])->middleware('integration.auth:ingest:write,experiments')->whereNumber('id')->name('experiments.results');

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
});

Route::middleware('integration.auth:read,brief')->controller(MetricsController::class)->group(function () {
    Route::get('brief/daily', 'daily')->name('brief.daily');
    Route::get('brief/weekly', 'weekly')->name('brief.weekly');
});

// N2 brand memory + truth guard (scope `read`; the check endpoint only reads and returns a verdict).
Route::get('brand-memory', [BrandMemoryController::class, 'index'])->middleware('integration.auth:read,brand_memory')->name('brand-memory.index');
Route::post('truth-guard/check', [BrandMemoryController::class, 'check'])->middleware('integration.auth:read,truth_guard')->name('truth-guard.check');

// Which Growth features are active for this tenant (n8n reads this to skip disabled flows).
Route::get('features', [\App\Http\Controllers\Api\Integration\FeatureController::class, 'index'])->middleware('integration.auth:read')->name('features.index');

// N4 engagement (ingest = scope `ingest:write`; reads = `read`). Nothing here sends a message to a customer.
Route::controller(\App\Http\Controllers\Api\Integration\EngagementController::class)->group(function () {
    Route::post('interactions', 'ingest')->middleware('integration.auth:ingest:write,interactions')->name('interactions.ingest');
    Route::get('interactions/{id}/suggested-reply', 'suggest')->whereNumber('id')->middleware('integration.auth:read,interactions')->name('interactions.suggest');
    Route::post('interactions/{id}/responded', 'responded')->whereNumber('id')->middleware('integration.auth:ingest:write,interactions')->name('interactions.responded');
    Route::post('tracked-links', 'link')->middleware('integration.auth:ingest:write,comment_to_cart')->name('tracked-links.store');
    Route::get('voc', 'voc')->middleware('integration.auth:read,voc')->name('voc.index');
    Route::get('reputation/alerts', 'alerts')->middleware('integration.auth:read,reputation_radar')->name('reputation.alerts');
    Route::get('missed-opportunities/weekly', 'missed')->middleware('integration.auth:read,missed_opportunities')->name('missed.weekly');
});
